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
    /** Caminho absoluto opcional para a pasta «condominios» no disco; por omissão: pasta condominios na raiz do site. */
    'local_condominios_path' => null,

    'ftp' => [
        'host'     => 'nuvipa.ftp.tb-hosting.com',
        'username' => 'dados@nuvipamapt',
        'password' => 'SUA_SENHA_FTP',
        /**
         * Pasta base no FTP (ex.: condominios). O código procura também em «documento» e «condominios».
         * Ex.: documento/123456789-2025.pdf ou condominios/2025/… com NIF no nome do ficheiro.
         */
        'base_path' => 'condominios',
        /** Em alojamento partilhado use em geral true; o código tenta também o modo contrário. */
        'passive'  => true,
        'timeout'  => 30,
    ],
    'mail' => [
        'to'   => 'condominio@nuvipama.pt',
        'from' => 'noreply@nuvipama.pt',
    ],
];
