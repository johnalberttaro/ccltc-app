<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait HandlesImageUpload
{
    // Rule used by every form that accepts a poster/image (max 5 MB)
    protected function imageRule(): string
    {
        return 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120';
    }

    // Save a new upload, or remove the current image if "remove_image" was ticked
    protected function applyImage(Request $request, Model $model, string $folder): void
    {
        if ($request->boolean('remove_image') && $model->image_path) {
            Storage::disk('public')->delete($model->image_path);
            $model->image_path = null;
        }

        if ($request->hasFile('image')) {
            if ($model->image_path) {
                Storage::disk('public')->delete($model->image_path);
            }
            $model->image_path = $request->file('image')->store($folder, 'public');
        }
    }

    protected function deleteImage(Model $model): void
    {
        if ($model->image_path) {
            Storage::disk('public')->delete($model->image_path);
        }
    }
}