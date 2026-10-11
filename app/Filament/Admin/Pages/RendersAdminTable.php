<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;

trait RendersAdminTable
{
    use InteractsWithTable;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }
}
