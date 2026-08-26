<?php

namespace App\Messaging;

use App\Messaging\SMS\AwsSnsDriver;
use App\Messaging\SMS\BrevoSmsDriver;
use App\Messaging\SMS\InfobipDriver;
use App\Messaging\SMS\SmSdevDriver;
use App\Messaging\SMS\TwilioSmsDriver;
use App\Messaging\SMS\VonageDriver;
use App\Messaging\SMS\ZenviaDriver;
use App\Messaging\WhatsApp\ChatProDriver;
use App\Messaging\WhatsApp\EvolutionApiDriver;
use App\Messaging\WhatsApp\MetaCloudDriver;
use App\Messaging\WhatsApp\TwilioWhatsAppDriver;
use App\Messaging\WhatsApp\WPPConnectDriver;
use App\Messaging\WhatsApp\ZApiDriver;

class MessagingProviderRegistry
{
    /**
     * Retorna todos os provedores disponíveis por canal.
     * Formato: [slug => ['label', 'channel', 'credential_keys']]
     */
    public static function all(): array
    {
        return [
            // WhatsApp
            'z-api'      => ['label' => 'Z-API',                        'channel' => 'whatsapp', 'credential_keys' => ZApiDriver::credentialKeys()],
            'evolution'  => ['label' => 'Evolution API',                 'channel' => 'whatsapp', 'credential_keys' => EvolutionApiDriver::credentialKeys()],
            'twilio-wa'  => ['label' => 'Twilio WhatsApp',               'channel' => 'whatsapp', 'credential_keys' => TwilioWhatsAppDriver::credentialKeys()],
            'meta-cloud' => ['label' => 'Meta Cloud API (WA Business)',  'channel' => 'whatsapp', 'credential_keys' => MetaCloudDriver::credentialKeys()],
            'chatpro'    => ['label' => 'ChatPro',                       'channel' => 'whatsapp', 'credential_keys' => ChatProDriver::credentialKeys()],
            'wppconnect' => ['label' => 'WPPConnect',                    'channel' => 'whatsapp', 'credential_keys' => WPPConnectDriver::credentialKeys()],
            // SMS
            'twilio-sms' => ['label' => 'Twilio SMS',                    'channel' => 'sms', 'credential_keys' => TwilioSmsDriver::credentialKeys()],
            'zenvia'     => ['label' => 'Zenvia SMS',                    'channel' => 'sms', 'credential_keys' => ZenviaDriver::credentialKeys()],
            'infobip'    => ['label' => 'Infobip SMS',                   'channel' => 'sms', 'credential_keys' => InfobipDriver::credentialKeys()],
            'aws-sns'    => ['label' => 'AWS SNS SMS',                   'channel' => 'sms', 'credential_keys' => AwsSnsDriver::credentialKeys()],
            'smsdev'     => ['label' => 'SMSdev (BR)',                   'channel' => 'sms', 'credential_keys' => SmSdevDriver::credentialKeys()],
            'brevo-sms'  => ['label' => 'Brevo SMS',                     'channel' => 'sms', 'credential_keys' => BrevoSmsDriver::credentialKeys()],
            'vonage'     => ['label' => 'Vonage (Nexmo) SMS',            'channel' => 'sms', 'credential_keys' => VonageDriver::credentialKeys()],
        ];
    }

    public static function forChannel(string $channel): array
    {
        return array_filter(self::all(), fn ($p) => $p['channel'] === $channel);
    }

    public static function make(string $providerSlug, array $credentials): MessagingDriver
    {
        return match ($providerSlug) {
            'z-api'      => new ZApiDriver($credentials['zapi_instance_id'] ?? '', $credentials['zapi_token'] ?? '', $credentials['zapi_client_token'] ?? ''),
            'evolution'  => new EvolutionApiDriver($credentials['evolution_base_url'] ?? '', $credentials['evolution_api_key'] ?? '', $credentials['evolution_instance'] ?? ''),
            'twilio-wa'  => new TwilioWhatsAppDriver($credentials['twilio_wa_account_sid'] ?? '', $credentials['twilio_wa_auth_token'] ?? '', $credentials['twilio_wa_from'] ?? ''),
            'meta-cloud' => new MetaCloudDriver($credentials['meta_wa_access_token'] ?? '', $credentials['meta_wa_phone_number_id'] ?? ''),
            'chatpro'    => new ChatProDriver($credentials['chatpro_session_key'] ?? ''),
            'wppconnect' => new WPPConnectDriver($credentials['wppconnect_base_url'] ?? '', $credentials['wppconnect_secret_key'] ?? '', $credentials['wppconnect_session'] ?? ''),
            'twilio-sms' => new TwilioSmsDriver($credentials['twilio_sms_account_sid'] ?? '', $credentials['twilio_sms_auth_token'] ?? '', $credentials['twilio_sms_from'] ?? ''),
            'zenvia'     => new ZenviaDriver($credentials['zenvia_api_key'] ?? '', $credentials['zenvia_from'] ?? ''),
            'infobip'    => new InfobipDriver($credentials['infobip_api_key'] ?? '', $credentials['infobip_base_url'] ?? '', $credentials['infobip_from'] ?? ''),
            'aws-sns'    => new AwsSnsDriver($credentials['aws_sns_access_key'] ?? '', $credentials['aws_sns_secret_key'] ?? '', $credentials['aws_sns_region'] ?? 'us-east-1', $credentials['aws_sns_sender_id'] ?? 'Msg'),
            'smsdev'     => new SmSdevDriver($credentials['smsdev_api_key'] ?? ''),
            'brevo-sms'  => new BrevoSmsDriver($credentials['brevo_sms_api_key'] ?? '', $credentials['brevo_sms_sender'] ?? ''),
            'vonage'     => new VonageDriver($credentials['vonage_api_key'] ?? '', $credentials['vonage_api_secret'] ?? '', $credentials['vonage_from'] ?? ''),
            default      => throw new \InvalidArgumentException("Provedor de mensagem desconhecido: {$providerSlug}"),
        };
    }
}
