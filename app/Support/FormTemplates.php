<?php

namespace App\Support;

/** Modelos prontos para começar rápido. */
class FormTemplates
{
    public static function all(): array
    {
        return [
            'blank' => [
                'name' => 'Em branco', 'icon' => 'fa-file', 'color' => '#7a8a85', 'layout' => 'classic',
                'description' => 'Comece do zero.',
                'fields' => [],
            ],
            'lead' => [
                'name' => 'Captura de leads', 'icon' => 'fa-bullseye', 'color' => '#0e7c66', 'layout' => 'classic',
                'description' => 'Nome, e-mail, WhatsApp e qualificação — ideal para campanhas.',
                'fields' => [
                    ['type' => 'short_text', 'label' => 'Qual é o seu nome?', 'required' => true],
                    ['type' => 'email', 'label' => 'Seu melhor e-mail', 'required' => true],
                    ['type' => 'phone', 'label' => 'WhatsApp', 'required' => true],
                    ['type' => 'short_text', 'label' => 'Empresa'],
                    ['type' => 'select', 'label' => 'Tamanho da empresa', 'options' => ['Só eu', '2 a 10', '11 a 50', '51 a 200', 'Mais de 200']],
                    ['type' => 'radio', 'label' => 'Qual o seu principal objetivo?', 'options' => ['Gerar mais leads', 'Entender meus clientes', 'Organizar processos', 'Outro']],
                    ['type' => 'consent', 'label' => 'Privacidade', 'required' => true, 'settings' => ['text' => 'Concordo em receber contato e com a política de privacidade.']],
                ],
            ],
            'nps' => [
                'name' => 'Pesquisa NPS', 'icon' => 'fa-gauge-high', 'color' => '#5aa889', 'layout' => 'conversational',
                'description' => 'Net Promoter Score com pergunta de acompanhamento condicional.',
                'fields' => [
                    ['type' => 'nps', 'label' => 'De 0 a 10, quanto você recomendaria nossa empresa a um amigo?', 'required' => true],
                    ['type' => 'long_text', 'label' => 'O que podemos melhorar?', 'logic' => ['enabled' => true, 'match' => 'all', 'rules' => [['field' => 0, 'op' => 'lt', 'value' => '7']]]],
                    ['type' => 'long_text', 'label' => 'O que você mais gosta em nós?', 'logic' => ['enabled' => true, 'match' => 'all', 'rules' => [['field' => 0, 'op' => 'gt', 'value' => '8']]]],
                    ['type' => 'yes_no', 'label' => 'Podemos entrar em contato para conversar sobre sua resposta?'],
                    ['type' => 'email', 'label' => 'Qual o seu e-mail?', 'logic' => ['enabled' => true, 'match' => 'all', 'rules' => [['field' => 3, 'op' => 'eq', 'value' => 'Sim']]]],
                ],
            ],
            'csat' => [
                'name' => 'Satisfação do cliente', 'icon' => 'fa-face-smile', 'color' => '#e0a526', 'layout' => 'chat',
                'description' => 'Avaliação por estrelas, escala de esforço e comentários.',
                'fields' => [
                    ['type' => 'rating', 'label' => 'Como você avalia sua experiência?', 'required' => true, 'settings' => ['max' => 5]],
                    ['type' => 'scale', 'label' => 'Quão fácil foi resolver o que você precisava?', 'settings' => ['min' => 1, 'max' => 7, 'label_min' => 'Muito difícil', 'label_max' => 'Muito fácil']],
                    ['type' => 'checkbox', 'label' => 'O que mais contribuiu para sua experiência?', 'options' => ['Atendimento', 'Rapidez', 'Preço', 'Qualidade do produto', 'Facilidade de uso']],
                    ['type' => 'long_text', 'label' => 'Quer deixar algum comentário?'],
                ],
            ],
            'event' => [
                'name' => 'Inscrição em evento', 'icon' => 'fa-calendar-check', 'color' => '#1d5fa8', 'layout' => 'card',
                'description' => 'Inscrição com dados pessoais, sessão e restrição alimentar.',
                'fields' => [
                    ['type' => 'statement', 'label' => 'Dados do participante'],
                    ['type' => 'short_text', 'label' => 'Nome completo', 'required' => true],
                    ['type' => 'email', 'label' => 'E-mail', 'required' => true],
                    ['type' => 'phone', 'label' => 'Celular'],
                    ['type' => 'short_text', 'label' => 'Cargo'],
                    ['type' => 'statement', 'label' => 'Participação'],
                    ['type' => 'radio', 'label' => 'Em qual turno você vai participar?', 'required' => true, 'options' => ['Manhã', 'Tarde', 'Dia inteiro']],
                    ['type' => 'yes_no', 'label' => 'Possui alguma restrição alimentar?'],
                    ['type' => 'short_text', 'label' => 'Qual restrição?', 'logic' => ['enabled' => true, 'match' => 'all', 'rules' => [['field' => 7, 'op' => 'eq', 'value' => 'Sim']]]],
                    ['type' => 'select', 'label' => 'Como ficou sabendo do evento?', 'options' => ['Instagram', 'LinkedIn', 'E-mail', 'Indicação', 'Google', 'Outro']],
                ],
            ],
            'job' => [
                'name' => 'Candidatura a vaga', 'icon' => 'fa-briefcase', 'color' => '#6d3fc0', 'layout' => 'classic',
                'description' => 'Dados, endereço por CEP, pretensão salarial e envio de currículo.',
                'fields' => [
                    ['type' => 'statement', 'label' => 'Seus dados'],
                    ['type' => 'short_text', 'label' => 'Nome completo', 'required' => true],
                    ['type' => 'email', 'label' => 'E-mail', 'required' => true],
                    ['type' => 'phone', 'label' => 'Celular / WhatsApp', 'required' => true],
                    ['type' => 'cpf_cnpj', 'label' => 'CPF', 'required' => true, 'settings' => ['doc' => 'cpf']],
                    ['type' => 'address', 'label' => 'Endereço', 'required' => true],
                    ['type' => 'statement', 'label' => 'Sobre a vaga'],
                    ['type' => 'select', 'label' => 'Vaga de interesse', 'required' => true, 'options' => ['Analista de marketing', 'Designer', 'Desenvolvedor(a)', 'Vendas', 'Atendimento']],
                    ['type' => 'currency', 'label' => 'Pretensão salarial (mensal)', 'settings' => ['min' => 0]],
                    ['type' => 'date', 'label' => 'A partir de quando pode começar?'],
                    ['type' => 'file', 'label' => 'Currículo', 'required' => true, 'settings' => ['accept' => 'documents', 'max_size' => 5, 'max_files' => 1]],
                    ['type' => 'url', 'label' => 'LinkedIn ou portfólio'],
                    ['type' => 'consent', 'label' => 'Privacidade', 'required' => true, 'settings' => ['text' => 'Autorizo o uso dos meus dados para este processo seletivo.']],
                ],
            ],
            'climate' => [
                'name' => 'Pesquisa de clima', 'icon' => 'fa-people-group', 'color' => '#b4472b', 'layout' => 'conversational',
                'description' => 'Grade de concordância, prioridades em ranking e escala deslizante.',
                'fields' => [
                    ['type' => 'matrix', 'label' => 'Quanto você concorda com as afirmações abaixo?', 'required' => true,
                        'options' => ['Discordo', 'Neutro', 'Concordo'],
                        'settings' => ['matrix_rows' => ['Tenho clareza sobre minhas metas', 'Recebo feedback com frequência', 'Tenho as ferramentas de que preciso', 'Recomendaria a empresa a um amigo']]],
                    ['type' => 'ranking', 'label' => 'Ordene o que é mais importante para você', 'options' => ['Salário', 'Flexibilidade', 'Crescimento', 'Ambiente', 'Benefícios']],
                    ['type' => 'slider', 'label' => 'De 0 a 100, qual seu nível de energia no trabalho hoje?', 'settings' => ['min' => 0, 'max' => 100, 'step' => 5, 'unit' => '%']],
                    ['type' => 'long_text', 'label' => 'O que mudaria primeiro se pudesse?'],
                ],
            ],
            'contact' => [
                'name' => 'Fale conosco', 'icon' => 'fa-envelope-open-text', 'color' => '#2c4a43', 'layout' => 'card',
                'description' => 'Formulário de contato simples para o seu site.',
                'fields' => [
                    ['type' => 'short_text', 'label' => 'Nome', 'required' => true],
                    ['type' => 'email', 'label' => 'E-mail', 'required' => true],
                    ['type' => 'select', 'label' => 'Assunto', 'required' => true, 'options' => ['Comercial', 'Suporte', 'Financeiro', 'Parcerias', 'Outro']],
                    ['type' => 'long_text', 'label' => 'Mensagem', 'required' => true],
                ],
            ],
        ];
    }

    /** Converte um modelo em campos prontos para gravar (resolvendo referências de lógica por índice). */
    public static function fields(string $key): array
    {
        $tpl = self::all()[$key] ?? self::all()['blank'];
        $keys = [];
        $out = [];
        foreach ($tpl['fields'] as $i => $f) {
            $keys[$i] = 'q'.($i + 1).'_'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4));
        }
        foreach ($tpl['fields'] as $i => $f) {
            $settings = $f['settings'] ?? [];
            if (isset($f['logic'])) {
                $logic = $f['logic'];
                // Regras referenciam campos pelo índice no modelo; troca pela chave gerada.
                $logic['rules'] = array_map(fn ($r) => ['field' => $keys[$r['field']]] + $r, $logic['rules']);
                $settings['logic'] = $logic;
            }
            $out[] = [
                'key' => $keys[$i],
                'type' => $f['type'],
                'label' => $f['label'],
                'required' => $f['required'] ?? false,
                'options' => $f['options'] ?? null,
                'settings' => $settings ?: null,
                'position' => $i,
            ];
        }

        return $out;
    }
}
