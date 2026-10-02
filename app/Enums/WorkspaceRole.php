<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Member = 'member';
    case Editor = 'editor';
    case Reviewer = 'reviewer';
    case Administrator = 'administrator';
}
