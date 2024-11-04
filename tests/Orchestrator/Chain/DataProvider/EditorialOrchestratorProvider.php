<?php

namespace App\Tests\Orchestrator\Chain\DataProvider;

/**
 * @author Juanma Santos <jmsantos@elconfidencial.com>
 */
class EditorialOrchestratorProvider
{
    public function EditorialIsValid(): array
    {
        return [
            [false, new \DateTime('3000-01-01 00:00:00')],
            [true, new \DateTime('1900-01-01 00:00:00')],
        ];
    }
}
