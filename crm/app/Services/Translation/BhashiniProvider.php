<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bhashini (Government of India, MeitY): free for registered users, 22 Indian languages,
 * runs in India. First a config call gives the service and an inference key, then the
 * translation itself. Register at bhashini.gov.in → My Profile: User ID and ULCA API key.
 */
class BhashiniProvider implements Provider
{
    public const CONFIG_URL = 'https://meity-auth.ulcacontrib.org/ulca/apis/v0/model/getModelsPipeline';
    public const DEFAULT_PIPELINE = '64392f96daac500b55c543cd'; // MeitY translation pipeline

    /** CRM code => Bhashini (ISO-639) code */
    public const CODES = [
        'en' => 'en', 'hi' => 'hi', 'mr' => 'mr', 'gu' => 'gu', 'bn' => 'bn', 'pa' => 'pa', 'ta' => 'ta', 'te' => 'te', 'kn' => 'kn',
        'ml' => 'ml', 'or' => 'or', 'as' => 'as', 'ur' => 'ur', 'ne' => 'ne', 'kok' => 'gom', 'mai' => 'mai', 'sd' => 'sd', 'ks' => 'ks',
        'doi' => 'doi', 'brx' => 'brx', 'sa' => 'sa', 'mni' => 'mni', 'sat' => 'sat',
    ];

    public function __construct(private string $userId, private string $apiKey, private string $pipelineId = self::DEFAULT_PIPELINE) {}

    public function supports(string $lang): bool
    {
        return isset(self::CODES[$lang]);
    }

    /**
     * $from may be "hi-Latn" (Hinglish) or "ta-Latn" (Tamil typed in English letters): those are
     * first written in their own script by Bhashini's transliteration, then translated.
     */
    public function translate(array $texts, ?string $from, string $to): array
    {
        if ($from !== null && str_ends_with($from, '-Latn') && $this->supports($base = substr($from, 0, -5)) && $base !== 'en') {
            $texts = $this->compute('transliteration', 'en', self::CODES[$base], $texts, ['isSentence' => true, 'numSuggestions' => 1]);
            $from = $base;
            if ($from === $to) {
                return $texts;
            }
        }
        if ($from === null || ! $this->supports($from) || ! $this->supports($to)) {
            throw new TranslationFailed(__('Bhashini needs to know the language of the message. Set the client\'s language in the chat, or write in English or Hindi.'));
        }

        return $this->compute('translation', self::CODES[$from], self::CODES[$to], $texts);
    }

    private function compute(string $task, string $src, string $tgt, array $texts, array $extra = []): array
    {
        $cfg = $this->config($task, $src, $tgt);
        try {
            $res = Http::withHeaders([$cfg['header'] => $cfg['key']])->acceptJson()->timeout(20)->post($cfg['url'], [
                'pipelineTasks' => [['taskType' => $task, 'config' => ['language' => ['sourceLanguage' => $src, 'targetLanguage' => $tgt], 'serviceId' => $cfg['serviceId']] + $extra]],
                'inputData' => ['input' => array_map(fn ($t) => ['source' => $t], array_values($texts))],
            ]);
        } catch (Throwable $e) {
            throw new TranslationFailed('Could not reach Bhashini.');
        }
        $out = $res->json('pipelineResponse.0.output');
        if (! $res->successful() || ! is_array($out) || count($out) !== count($texts)) {
            Cache::forget($this->cacheKey($task, $src, $tgt)); // the inference key may have changed
            throw new TranslationFailed('Bhashini: '.mb_substr((string) ($res->json('detail.message') ?? $res->json('message') ?? 'HTTP '.$res->status()), 0, 200));
        }

        // Transliteration may answer with a list of suggestions: the first is the best.
        return array_map(fn ($o) => (string) (is_array($o['target'] ?? null) ? ($o['target'][0] ?? '') : ($o['target'] ?? '')), $out);
    }

    private function cacheKey(string $task, string $src, string $tgt): string
    {
        return 'bhashini:cfg:'.sha1($this->userId.'|'.$this->pipelineId).':'.$task.':'.$src.':'.$tgt;
    }

    /** Service ID, address and key for a task and language pair; kept for a day. */
    private function config(string $task, string $src, string $tgt): array
    {
        $cached = Cache::get($this->cacheKey($task, $src, $tgt));
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $res = Http::withHeaders(['userID' => $this->userId, 'ulcaApiKey' => $this->apiKey])->acceptJson()->timeout(20)->post(self::CONFIG_URL, [
                'pipelineTasks' => [['taskType' => $task, 'config' => ['language' => ['sourceLanguage' => $src, 'targetLanguage' => $tgt]]]],
                'pipelineRequestConfig' => ['pipelineId' => $this->pipelineId],
            ]);
        } catch (Throwable $e) {
            throw new TranslationFailed('Could not reach Bhashini.');
        }
        $serviceId = $res->json('pipelineResponseConfig.0.config.0.serviceId');
        $url = (string) $res->json('pipelineInferenceAPIEndPoint.callbackUrl');
        $header = (string) ($res->json('pipelineInferenceAPIEndPoint.inferenceApiKey.name') ?: 'Authorization');
        $key = (string) $res->json('pipelineInferenceAPIEndPoint.inferenceApiKey.value');
        if (! $res->successful() || ! $serviceId || $key === '') {
            throw new TranslationFailed('Bhashini: '.($res->status() === 401 || $res->status() === 403 ? 'the User ID or API key is wrong.' : 'this language pair is not available ('.$src.' → '.$tgt.').'));
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! preg_match('/(^|\.)bhashini\.gov\.in$/', $host)) {
            throw new TranslationFailed('Bhashini gave an unexpected address; translation stopped for safety.');
        }
        if (! preg_match('/^[A-Za-z][A-Za-z0-9\-]{0,40}$/', $header)) {
            $header = 'Authorization';
        }
        $cfg = ['serviceId' => (string) $serviceId, 'url' => $url, 'header' => $header, 'key' => $key];
        Cache::put($this->cacheKey($task, $src, $tgt), $cfg, now()->addDay());

        return $cfg;
    }
}
