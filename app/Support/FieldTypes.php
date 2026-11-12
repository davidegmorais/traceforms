<?php

namespace App\Support;

/**
 * Catálogo de tipos de campo suportados pelo construtor.
 */
class FieldTypes
{
    public const TYPES = [
        'short_text' => ['label' => 'Texto curto', 'icon' => 'fa-font', 'group' => 'Texto'],
        'long_text'  => ['label' => 'Texto longo', 'icon' => 'fa-align-left', 'group' => 'Texto'],
        'email'      => ['label' => 'E-mail', 'icon' => 'fa-at', 'group' => 'Contato'],
        'phone'      => ['label' => 'Telefone', 'icon' => 'fa-phone', 'group' => 'Contato'],
        'url'        => ['label' => 'Site / URL', 'icon' => 'fa-link', 'group' => 'Contato'],
        'cpf_cnpj'   => ['label' => 'CPF / CNPJ', 'icon' => 'fa-id-card', 'group' => 'Brasil'],
        'address'    => ['label' => 'Endereço (CEP)', 'icon' => 'fa-location-dot', 'group' => 'Brasil'],
        'number'     => ['label' => 'Número', 'icon' => 'fa-hashtag', 'group' => 'Números e datas'],
        'currency'   => ['label' => 'Valor em dinheiro', 'icon' => 'fa-brazilian-real-sign', 'group' => 'Números e datas'],
        'slider'     => ['label' => 'Controle deslizante', 'icon' => 'fa-sliders', 'group' => 'Números e datas'],
        'date'       => ['label' => 'Data', 'icon' => 'fa-calendar-day', 'group' => 'Números e datas'],
        'time'       => ['label' => 'Hora', 'icon' => 'fa-clock', 'group' => 'Números e datas'],
        'radio'      => ['label' => 'Escolha única', 'icon' => 'fa-circle-dot', 'group' => 'Escolha', 'options' => true],
        'checkbox'   => ['label' => 'Múltipla escolha', 'icon' => 'fa-square-check', 'group' => 'Escolha', 'options' => true],
        'select'     => ['label' => 'Lista suspensa', 'icon' => 'fa-caret-down', 'group' => 'Escolha', 'options' => true],
        'yes_no'     => ['label' => 'Sim / Não', 'icon' => 'fa-toggle-on', 'group' => 'Escolha'],
        'ranking'    => ['label' => 'Ordenar por preferência', 'icon' => 'fa-arrow-down-1-9', 'group' => 'Escolha', 'options' => true],
        'matrix'     => ['label' => 'Matriz (grade)', 'icon' => 'fa-table-cells', 'group' => 'Escolha', 'options' => true],
        'rating'     => ['label' => 'Avaliação (estrelas)', 'icon' => 'fa-star', 'group' => 'Opinião'],
        'nps'        => ['label' => 'NPS (0–10)', 'icon' => 'fa-gauge-high', 'group' => 'Opinião'],
        'scale'      => ['label' => 'Escala de opinião', 'icon' => 'fa-ellipsis', 'group' => 'Opinião'],
        'file'       => ['label' => 'Envio de arquivo', 'icon' => 'fa-paperclip', 'group' => 'Arquivos'],
        'signature'  => ['label' => 'Assinatura', 'icon' => 'fa-signature', 'group' => 'Arquivos'],
        'consent'    => ['label' => 'Consentimento (LGPD)', 'icon' => 'fa-shield-halved', 'group' => 'Outros'],
        'statement'  => ['label' => 'Texto / Seção', 'icon' => 'fa-heading', 'group' => 'Outros', 'input' => false],
        'hidden'     => ['label' => 'Campo oculto', 'icon' => 'fa-eye-slash', 'group' => 'Outros'],
    ];

    /** Grupos de tipos de arquivo aceitos no campo de envio. */
    public const ACCEPT = [
        'any' => ['label' => 'Qualquer arquivo comum', 'mimes' => 'jpg,jpeg,png,gif,webp,heic,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,mp3,mp4,mov'],
        'images' => ['label' => 'Imagens', 'mimes' => 'jpg,jpeg,png,gif,webp,heic'],
        'documents' => ['label' => 'Documentos (PDF, Word, Excel)', 'mimes' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt'],
        'pdf' => ['label' => 'Somente PDF', 'mimes' => 'pdf'],
        'media' => ['label' => 'Áudio e vídeo', 'mimes' => 'mp3,mp4,mov,m4a,wav'],
    ];

    public const ADDRESS_PARTS = [
        'cep' => 'CEP', 'street' => 'Rua', 'number' => 'Número', 'complement' => 'Complemento',
        'district' => 'Bairro', 'city' => 'Cidade', 'state' => 'UF',
    ];

    public static function all(): array
    {
        return self::TYPES;
    }

    public static function grouped(): array
    {
        $out = [];
        foreach (self::TYPES as $key => $meta) {
            $out[$meta['group']][$key] = $meta;
        }

        return $out;
    }

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function hasOptions(string $type): bool
    {
        return (bool) (self::TYPES[$type]['options'] ?? false);
    }

    public static function isInput(string $type): bool
    {
        return self::TYPES[$type]['input'] ?? true;
    }

    public static function isFile(string $type): bool
    {
        return in_array($type, ['file', 'signature'], true);
    }

    /** Tipos cujas respostas podem ser agregadas em gráficos de contagem. */
    public static function isChartable(string $type): bool
    {
        return in_array($type, ['radio', 'checkbox', 'select', 'yes_no', 'rating', 'nps', 'scale', 'consent'], true);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? $type;
    }

    public static function icon(string $type): string
    {
        return self::TYPES[$type]['icon'] ?? 'fa-question';
    }
}
