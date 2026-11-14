<?php

namespace App\Services\Leads;

/** One enquiry from any platform, in the CRM's own words. */
class LeadData
{
    public ?string $externalId = null;
    public ?string $name = null;
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $firm = null;
    public ?string $city = null;
    public ?string $state = null;
    public ?string $product = null;
    public ?string $message = null;
    public ?string $campaign = null;
    public ?string $source = null;   // overrides the platform's source (e.g. Instagram Ads)
    public bool $test = false;
    /** @var string[] */
    public array $tags = [];
    /** @var string[] names of the fields received (never values), to help set up a new platform */
    public array $fieldNames = [];

    public function hasContact(): bool
    {
        return $this->phone !== null || $this->email !== null;
    }

    /** A stable id when the platform sends none, so a resend is not a second lead. */
    public function dedupeId(): string
    {
        return $this->externalId ?? 'h:'.substr(hash('sha256', implode('|', [$this->phone, $this->email, $this->product, $this->message])), 0, 40);
    }
}
