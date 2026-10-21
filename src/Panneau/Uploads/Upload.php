<?php

namespace Panneau\Uploads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file the browser uploaded to the disk itself, before a value uses it: a media, an import, a
 * file field. Its id (`upl_…`) is the last segment of its key.
 *
 * @property string $uid
 * @property string $user_id
 * @property UploadStatus $status
 * @property string $disk
 * @property string $path
 * @property string|null $provider_upload_id
 * @property string|null $name
 * @property string|null $mime
 * @property int|null $size
 * @property Carbon $expires_at
 */
final class Upload extends Model
{
    protected $table = 'panneau_uploads';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UploadStatus::class,
            'size' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->status === UploadStatus::Completed;
    }

    /**
     * Reads the file from the disk.
     *
     * @return resource|null
     */
    public function readStream()
    {
        return Storage::disk($this->disk)->readStream($this->path);
    }
}
