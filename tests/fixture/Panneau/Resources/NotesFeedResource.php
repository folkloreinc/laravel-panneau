<?php

namespace TestApp\Panneau\Resources;

use Panneau\Lists\ResourceList;

/**
 * The notes, in a list paged with cursors.
 */
class NotesFeedResource extends NotesResource
{
    public function list(ResourceList $list): ResourceList
    {
        return parent::list($list)->cursorPagination()->perPage(2);
    }
}
