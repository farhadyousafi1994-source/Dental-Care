<?php
namespace App\Events;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Domains\Websites\Website;
/** Extension point for queued integrations; no external provisioning is implied. */
class WebsiteProvisioned implements ShouldDispatchAfterCommit {
 use Dispatchable, SerializesModels;
 public function __construct(public Website $website) {}
}
