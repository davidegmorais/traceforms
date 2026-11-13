<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Form;
use App\Models\Membership;
use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    protected function account(): Account
    {
        return app('account');
    }

    protected function membership(): Membership
    {
        return app('membership');
    }

    /**
     * Garante que o formulário pertence à conta ativa e que o usuário tem a habilidade.
     */
    protected function authorizeForm(Form $form, string $ability): Form
    {
        abort_unless($form->account_id === $this->account()->id, 404);
        Gate::authorize($ability, $form);

        return $form;
    }

    /** Formulários visíveis ao membro atual (respeita restrição por formulário). */
    protected function visibleForms()
    {
        $q = $this->account()->forms();
        $m = $this->membership();
        if ($m->isRestricted()) {
            $q->whereIn('id', $m->form_ids);
        }

        return $q;
    }
}
