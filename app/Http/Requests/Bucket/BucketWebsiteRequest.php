<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\DTO\Bucket\WebsiteData;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageCapability;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * enabled = false — выключить хостинг. Режим index — сайт из бакета, redirect — перенаправление всех запросов.
 */
final class BucketWebsiteRequest extends BucketRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ManageWebsite;
    }

    protected function capability(): StorageCapability
    {
        return StorageCapability::Website;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'mode' => ['required_if:enabled,true', 'nullable', 'in:index,redirect'],
            'index_document' => ['required_if:mode,index', 'nullable', 'string', 'max:1024', 'not_regex:/\//'],
            'error_document' => ['nullable', 'string', 'max:1024'],
            'redirect_host' => ['required_if:mode,redirect', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'redirect_protocol' => ['nullable', 'in:http,https'],
            'routing_rules' => ['nullable', 'string', 'json', 'max:20480'],
        ];
    }

    public function toData(): ?WebsiteData
    {
        if (! $this->boolean('enabled')) {
            return null;
        }

        $redirect = $this->validated('mode') === 'redirect';
        $rules = $this->validated('routing_rules');

        return new WebsiteData([
            'indexDocument' => $redirect ? null : $this->validated('index_document'),
            'errorDocument' => $redirect ? null : ($this->validated('error_document') ?: null),
            'redirectHost' => $redirect ? $this->validated('redirect_host') : null,
            'redirectProtocol' => $redirect ? ($this->validated('redirect_protocol') ?: null) : null,
            'routingRules' => ! $redirect && is_string($rules) && trim($rules) !== '' && json_decode($rules) !== [] ? $rules : null,
        ]);
    }
}
