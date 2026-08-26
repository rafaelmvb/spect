<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class YouTubeTranscriptService
{
    public function extractVideoId(string $url): ?string
    {
        preg_match(
            '/(?:youtube\.com\/watch\?.*?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/',
            $url,
            $m
        );
        return $m[1] ?? null;
    }

    /**
     * Retorna transcrição como array de {start: float (segundos), text: string}.
     * Retorna null quando transcrição não está disponível.
     * Resultado é cacheado por 24h.
     */
    public function getTranscript(string $videoId): ?array
    {
        return Cache::remember("yt_transcript_{$videoId}", 86400, function () use ($videoId) {
            return $this->fetchTranscript($videoId);
        });
    }

    /**
     * Formata a transcrição para inserção no system prompt da IA.
     * Limita a ~6000 caracteres para não explodir o contexto.
     */
    public function formatForPrompt(array $transcript, string $videoId): string
    {
        $lines = [];
        foreach ($transcript as $item) {
            $secs = (int) floor((float) $item['start']);
            $min  = (int) floor($secs / 60);
            $sec  = $secs % 60;
            $lines[] = sprintf('[%d:%02d] %s', $min, $sec, $item['text']);
        }

        $full = implode("\n", $lines);

        if (strlen($full) > 6000) {
            $full = substr($full, 0, 5900) . "\n[... transcrição continua ...]";
        }

        return $full;
    }

    private function fetchTranscript(string $videoId): ?array
    {
        $trackUrl = $this->getCaptionTrackUrl($videoId);
        if (! $trackUrl) {
            return null;
        }

        return $this->parseTranscriptXml($trackUrl);
    }

    private function getCaptionTrackUrl(string $videoId): ?string
    {
        try {
            $response = Http::timeout(8)->withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            ])->get('https://www.youtube.com/watch', ['v' => $videoId]);

            if (! $response->ok()) {
                return null;
            }

            return $this->parseCaptionUrl($response->body());
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseCaptionUrl(string $html): ?string
    {
        $pos = strpos($html, '"captionTracks"');
        if ($pos === false) {
            return null;
        }

        // Pega chunk após "captionTracks" para trabalhar
        $chunk = substr($html, $pos, 10000);

        // Extrai pares (baseUrl, languageCode) — URLs têm sequências Unicode escapadas
        preg_match_all(
            '/"baseUrl"\s*:\s*"(https:[^"]+timedtext[^"]+)"[^{}{]{0,300}"languageCode"\s*:\s*"([^"]+)"/',
            $chunk,
            $matches,
            PREG_SET_ORDER
        );

        $tracks = [];
        foreach ($matches as $m) {
            $url = json_decode('"' . $m[1] . '"'); // decodifica &, =, etc.
            if ($url) {
                $tracks[] = ['url' => $url, 'lang' => strtolower($m[2])];
            }
        }

        // Fallback: só baseUrl sem languageCode
        if (empty($tracks)) {
            preg_match('/"baseUrl"\s*:\s*"(https:[^"]+timedtext[^"]+)"/', $chunk, $m);
            if (isset($m[1])) {
                return json_decode('"' . $m[1] . '"');
            }
            return null;
        }

        // Prefere: pt-BR > pt > en > qualquer
        foreach (['pt-br', 'pt', 'en-us', 'en'] as $lang) {
            foreach ($tracks as $t) {
                if ($t['lang'] === $lang) {
                    return $t['url'];
                }
            }
        }

        return $tracks[0]['url'];
    }

    private function parseTranscriptXml(string $trackUrl): ?array
    {
        try {
            $response = Http::timeout(8)->get($trackUrl);
            if (! $response->ok()) {
                return null;
            }

            $xml = @simplexml_load_string($response->body());
            if (! $xml) {
                return null;
            }

            $segments = [];
            foreach ($xml->text as $node) {
                $start = (float) ($node['start'] ?? 0);
                $text  = html_entity_decode((string) $node, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text  = trim(preg_replace('/\s+/', ' ', $text));
                if ($text !== '') {
                    $segments[] = ['start' => $start, 'text' => $text];
                }
            }

            return empty($segments) ? null : $segments;
        } catch (\Throwable) {
            return null;
        }
    }
}
