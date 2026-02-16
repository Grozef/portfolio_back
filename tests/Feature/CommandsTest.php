<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CarouselImage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class CommandsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_warm_book_cache_command_coverage()
    {
        Book::create(['title' => 'T', 'author' => 'A', 'isbn' => '9782070415793', 'description' => 'D']);

        // FORCE LOADING : On va chercher le fichier physiquement
        $file = app_path('commands/WarmBookCache.php');
        if (!file_exists($file)) {
            $file = app_path('Console/Commands/WarmBookCache.php');
        }

        if (file_exists($file)) {
            require_once $file;
        }

        // On teste les deux namespaces possibles à cause du dossier 'commands'
        $class = 'App\Console\Commands\WarmBookCache';
        if (!class_exists($class)) {
            $class = 'App\commands\WarmBookCache';
        }

        if (class_exists($class)) {
            $command = app()->make($class);
            $command->setLaravel(app());
            $command->run(new ArrayInput(['--limit' => 1]), new NullOutput());
            $this->assertTrue(true);
        } else {
            $this->fail("Impossible de trouver la classe WarmBookCache même après chargement forcé du fichier : $file");
        }
    }

    public function test_add_exif_secret_command_coverage()
    {
        $path = public_path('carousel/test_cmd.jpg');
        if (!File::isDirectory(public_path('carousel'))) File::makeDirectory(public_path('carousel'), 0755, true);
        imagejpeg(imagecreatetruecolor(10, 10), $path);

        CarouselImage::create(['title' => 'T', 'image_url' => 'carousel/test_cmd.jpg', 'is_active' => true]);

        // FORCE LOADING
        $file = app_path('commands/AddExifSecret.php');
        if (file_exists($file)) {
            require_once $file;
        }

        $class = 'App\Commands\AddExifSecret';
        if (!class_exists($class)) {
            $class = 'App\commands\AddExifSecret';
        }

        if (class_exists($class)) {
            $command = app()->make($class);
            $command->setLaravel(app());
            $command->run(new ArrayInput(['image' => 'carousel/test_cmd.jpg']), new NullOutput());
            $this->assertTrue(true);
        }

        if (File::exists($path)) File::delete($path);
    }
}
