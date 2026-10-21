<?php

namespace Panneau\Uploads;

/**
 * Where an upload is: `open` while the browser sends it, `completed` once the file is on the disk.
 */
enum UploadStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
}
