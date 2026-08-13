<?php

namespace App\Enums;

/**
 * Papel do coordenador de discursos no time.
 */
enum CoordinatorRole: string
{
    case Responsible = 'responsible';
    case Helper = 'helper';
}
