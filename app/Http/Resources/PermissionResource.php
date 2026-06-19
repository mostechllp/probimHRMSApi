<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role_id' => $this->role_id,
            'module_id' => $this->module_id,
            'module_name' => $this->module ? $this->module->name : null,
            'module_slug' => $this->module ? $this->module->slug : null,
            'can_read' => (bool) $this->can_read,
            'can_edit' => (bool) $this->can_edit,
            'can_delete' => (bool) $this->can_delete,
        ];
    }
}
