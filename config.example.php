<?php
/**
 * Copie este ficheiro para config.local.php e preencha os valores.
 * O config.local.php não deve ser commitado (está no .gitignore).
 */
return [
    /**
     * Onde procurar os PDF/HTML:
     * - auto: primeiro pasta local condominios/, depois FTP
     * - local: só disco (condominios/{ANO}/ficheiro com NIF no nome)
     * - ftp: só FTP (condominios/{ANO}/ficheiro com NIF no nome; fallback: raiz com NIF e ano no nome)
     */
    'document_storage' => 'auto',
    /** Caminho absoluto opcional para a pasta onde ficam os ficheiros (por omissão: www/Condominio na raiz do site). */
    'local_condominios_path' => null,

    'ftp' => [
        'host'     => 'nuvipa.ftp.tb-hosting.com',
        'username' => 'dados@nuvipamapt',
        'password' => 'SUA_SENHA_FTP',
        /**
         * Pasta base no FTP. Novo padrão recomendado: www/Condominio (sem subpastas por ano).
         * O código ainda tenta também pastas antigas (condominios/{ANO}, documento/{ANO}, etc).
         */
        'base_path' => 'www/Condominio',
        /** Em alojamento partilhado use em geral true; o código tenta também o modo contrário. */
        'passive'  => true,
        'timeout'  => 30,
    ],
    'mail' => [
        'to'   => 'condominio@nuvipama.pt',
        'from' => 'noreply@nuvipama.pt',
    ],
];
