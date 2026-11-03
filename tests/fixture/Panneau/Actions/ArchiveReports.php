<?php

namespace TestApp\Panneau\Actions;

use Illuminate\Support\Collection;
use Panneau\Actions\Action;
use Panneau\Actions\ActionResult;
use Panneau\Actions\ActionValues;

/**
 * An action authorized by the `runAction()` of ReportPolicy.
 */
class ArchiveReports extends Action
{
    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        foreach ($items as $report) {
            $report->title = 'Archived';
            $report->save();
        }

        return $result;
    }
}
