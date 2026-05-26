<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $daysUntilExpiry = $this->expiry_date ? now()->startOfDay()->diffInDays($this->expiry_date, false) : null;
        $expiryStatus = match (true) {
            $daysUntilExpiry === null => 'not_tracked',
            $daysUntilExpiry < 0 => 'expired',
            $daysUntilExpiry <= 30 => 'expiring_soon',
            default => 'valid',
        };
        $isPreviewable = str_starts_with((string) $this->mime_type, 'image/');

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'document_type' => $this->document_type,
            'title' => $this->title,
            'original_file_name' => $this->original_file_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'issue_date' => optional($this->issue_date)->toDateString(),
            'expiry_date' => optional($this->expiry_date)->toDateString(),
            'days_until_expiry' => $daysUntilExpiry,
            'expiry_status' => $expiryStatus,
            'status' => $this->status,
            'uploaded_by' => $this->uploaded_by,
            'download_url' => "/api/documents/{$this->id}/download",
            'preview_url' => $isPreviewable ? "/api/documents/{$this->id}/preview" : null,
            'is_previewable' => $isPreviewable,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
