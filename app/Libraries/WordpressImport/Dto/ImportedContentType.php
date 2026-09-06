<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport\Dto;

enum ImportedContentType: string
{
    case Post = 'post';
    case Page = 'page';
}
