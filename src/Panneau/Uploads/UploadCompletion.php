<?php

namespace Panneau\Uploads;

/**
 * What completing an upload found on the disk.
 */
enum UploadCompletion
{
    case Completed;

    /**
     * The disk doesn't have the file yet.
     */
    case Missing;

    /**
     * The file was larger than `panneau.uploads.max_size`: it is deleted, with its upload.
     */
    case TooLarge;
}
