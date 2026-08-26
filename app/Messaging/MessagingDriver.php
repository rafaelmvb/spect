<?php

namespace App\Messaging;

interface MessagingDriver
{
    /**
     * Envia mensagem para um número de telefone.
     * @return array{success: bool, error: string|null}
     */
    public function send(string $phone, string $message): array;

    /** Retorna o nome legível do provedor */
    public function label(): string;

    /** Chaves de configuração necessárias */
    public static function credentialKeys(): array;
}
