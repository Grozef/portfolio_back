<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\OpenLibraryService;
use App\Services\GoogleBooksService;
use Illuminate\Console\Command;

/**
 * Warm Book Cache Command
 *
 * Refreshes book data from external APIs for books with expired cache.
 * Useful for keeping book information up to date without on-demand delays.
 *
 * Usage:
 *   php artisan books:warm-cache
 *   php artisan books:warm-cache --force
 *
 * @package App\Console\Commands
 */
class WarmBookCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'books:warm-cache
                            {--force : Refresh all books regardless of cache age}
                            {--limit= : Maximum number of books to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up book data cache from external APIs';

    /**
     * Execute the console command.
     */
    public function handle(
        OpenLibraryService $openLibrary,
        GoogleBooksService $googleBooks
    ): int {
        $this->info('Starting book cache warming...');

        // Build query for books to refresh
        $query = Book::whereNotNull('isbn');

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('cached_at')
                  ->orWhere('cached_at', '<', now()->subDays(30));
            });
        }

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $query->limit($limit);
        }

        $books = $query->get();

        if ($books->isEmpty()) {
            $this->info('No books need cache refresh.');
            return self::SUCCESS;
        }

        $this->info("Found {$books->count()} books to refresh");

        $bar = $this->output->createProgressBar($books->count());
        $bar->start();

        $stats = [
            'success' => 0,
            'openlibrary' => 0,
            'google_books' => 0,
            'failed' => 0,
        ];

        foreach ($books as $book) {
            // Try OpenLibrary first
            $data = $openLibrary->getBookByIsbn($book->isbn);

            if ($data) {
                $data['source'] = 'openlibrary';
                $book->updateCache($data);
                $stats['success']++;
                $stats['openlibrary']++;
                $this->newLine();
                $this->line("  ✓ {$book->title} (OpenLibrary)");
            } else {
                // Fallback to Google Books
                $data = $googleBooks->getBookByIsbn($book->isbn);

                if ($data) {
                    $data['source'] = 'google_books';
                    $book->updateCache($data);
                    $stats['success']++;
                    $stats['google_books']++;
                    $this->newLine();
                    $this->line("  ✓ {$book->title} (Google Books)");
                } else {
                    $stats['failed']++;
                    $this->newLine();
                    $this->warn("  ✗ {$book->title} - not found in any provider");
                }
            }

            $bar->advance();

            // Rate limiting - be nice to APIs
            sleep(1);
        }

        $bar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('Cache warming complete!');
        $this->table(
            ['Source', 'Count'],
            [
                ['OpenLibrary', $stats['openlibrary']],
                ['Google Books', $stats['google_books']],
                ['Failed', $stats['failed']],
                ['Total Success', $stats['success']],
            ]
        );

        return self::SUCCESS;
    }
}