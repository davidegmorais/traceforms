<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'accounts' => app(\App\Services\TraceId\AccountChoices::class)->for($request->user()),
            'sessions' => config('session.driver') === 'database'
                ? \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->limit(10)->get()
                : collect(),
        ]);
    }

    /** Recebe a foto, corta em quadrado e reduz para 320×320 (se o GD estiver disponível). */
    public function avatar(Request $request)
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']], [
            'avatar.image' => 'Envie uma imagem (JPG, PNG ou WebP).',
            'avatar.mimes' => 'Envie uma imagem JPG, PNG ou WebP.',
            'avatar.max' => 'A foto deve ter até 4 MB.',
        ]);
        $user = $request->user();
        $file = $request->file('avatar');
        $path = 'avatars/'.$user->id.'-'.Str::random(8).'.jpg';

        $binary = $this->squareJpeg($file->getRealPath(), 320);
        if ($binary === null) { // sem GD: guarda o original
            $path = 'avatars/'.$user->id.'-'.Str::random(8).'.'.$file->extension();
            $binary = file_get_contents($file->getRealPath());
        }
        Storage::disk('local')->put($path, $binary);

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => $path]);

        return back()->with('success', 'Foto atualizada.');
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('success', 'Foto removida.');
    }

    /** Encerra as outras sessões do TraceForms (o login no Trace ID não é afetado). */
    public function logoutOthers(Request $request)
    {
        if (config('session.driver') === 'database') {
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        }

        return back()->with('success', 'Você saiu dos outros dispositivos.');
    }

    /** Serve a foto de um usuário (somente para usuários autenticados). */
    public function showAvatar(User $user)
    {
        abort_unless($user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

        return response()->file(Storage::disk('local')->path($user->avatar_path), [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    private function squareJpeg(string $src, int $size): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $img = @imagecreatefromstring(file_get_contents($src));
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $side = min($w, $h);
        $out = imagecreatetruecolor($size, $size);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);
        ob_start();
        imagejpeg($out, null, 86);
        $data = ob_get_clean();
        imagedestroy($img);
        imagedestroy($out);

        return $data;
    }
}
