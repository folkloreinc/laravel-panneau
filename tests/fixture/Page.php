<?php

class Page implements PageContract
{
    public function id(): string {
        return 'id';
    }

    public function title(): string {
        return 'title';
    }
}
