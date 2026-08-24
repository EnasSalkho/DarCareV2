<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateModule extends Command
{

    protected $signature = 'make:module {name}';
    protected $description = 'إنشاء موديول جديد بالكامل داخل مجلد app/Modules';

    public function handle()
    {
        $name = ucfirst($this->argument('name'));
        $basePath = app_path("Modules/{$name}");

        if (File::exists($basePath)) {
            $this->error("الموديول {$name} موجود بالفعل!");
            return;
        }


        $directories = [
            $basePath,
            "{$basePath}/Contracts",
            "{$basePath}/Services",
            "{$basePath}/Http/Controllers/Admin",
            "{$basePath}/routes",
            "{$basePath}/Providers",
        ];

        foreach ($directories as $dir) {
            File::makeDirectory($dir, 0755, true);
        }

        $routeContent = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\nRoute::prefix('v1/admin')->group(function () {\n    // روابط الأدمن لموديول {$name}\n});\n";
        File::put("{$basePath}/routes/api.php", $routeContent);

        $this->info("تم إنشاء موديول {$name} بكافة مجلداته بنجاح باهر! 🚀");
    }
}
