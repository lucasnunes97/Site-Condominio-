<?php

declare(strict_types=1);

/**
 * Copie para portal_users.local.php e preencha (o .local não deve ir para o git).
 *
 * Cada entrada tem:
 * - nif: 9 dígitos do condómino
 * - senha: palavra-passe de acesso ao portal
 * - condo_slug: prefixo do condomínio (sem "__"), igual ao slug extraído do HTML após padronização (ex.: avenida-republica-2427)
 *
 * @return list<array{nif: string, senha: string, condo_slug: string}>
 */
function portal_users_config(): array
{
    return [
        [
            'nif' => '111111111',
            'senha' => 'ALTERAR',
            'condo_slug' => 'edificio-avenida',
        ],
        [
            'nif' => '222222222',
            'senha' => 'ALTERAR',
            'condo_slug' => 'outro-condominio',
        ],
    ];
}
