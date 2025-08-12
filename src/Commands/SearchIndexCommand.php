<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\SearchIndexService;
use App\Models\SearchAnalytic;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI Command for managing the search index
 * 
 * Usage:
 * php bin/console search:index --rebuild
 * php bin/console search:index --stats
 * php bin/console search:index --cleanup
 * php bin/console search:index --bulk-index=stories
 */
class SearchIndexCommand extends Command
{
    protected static $defaultName = 'search:index';
    protected static $defaultDescription = 'Manage the search index (rebuild, stats, cleanup)';

    public function __construct(
        private SearchIndexService $searchIndexService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Manage the search index and analytics')
            ->setHelp('This command allows you to manage the search index including rebuilding, stats, and cleanup operations.')
            ->addOption(
                'rebuild',
                'r',
                InputOption::VALUE_NONE,
                'Rebuild the entire search index'
            )
            ->addOption(
                'stats',
                's',
                InputOption::VALUE_NONE,
                'Show search index and analytics statistics'
            )
            ->addOption(
                'cleanup',
                'c',
                InputOption::VALUE_NONE,
                'Clean up old analytics data and optimize index'
            )
            ->addOption(
                'bulk-index',
                'b',
                InputOption::VALUE_REQUIRED,
                'Bulk index specific content type (stories, comments, users)'
            )
            ->addOption(
                'offset',
                'o',
                InputOption::VALUE_REQUIRED,
                'Starting offset for bulk indexing',
                0
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit for bulk indexing batch',
                100
            )
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of days for analytics (used with --stats)',
                30
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Lobsters Search Index Management');

        // Handle rebuild option
        if ($input->getOption('rebuild')) {
            return $this->rebuildIndex($io);
        }

        // Handle stats option
        if ($input->getOption('stats')) {
            return $this->showStats($io, (int) $input->getOption('days'));
        }

        // Handle cleanup option
        if ($input->getOption('cleanup')) {
            return $this->cleanup($io);
        }

        // Handle bulk index option
        if ($bulkType = $input->getOption('bulk-index')) {
            return $this->bulkIndex(
                $io, 
                $bulkType, 
                (int) $input->getOption('offset'),
                (int) $input->getOption('limit')
            );
        }

        // If no options provided, show help
        $io->warning('No action specified. Use --help to see available options.');
        return Command::INVALID;
    }

    private function rebuildIndex(SymfonyStyle $io): int
    {
        $io->section('Rebuilding Search Index');
        
        $io->progressStart();
        
        try {
            $results = $this->searchIndexService->rebuildIndex();
            
            $io->progressFinish();
            $io->newLine();
            
            if (isset($results['global_error'])) {
                $io->error('Index rebuild failed: ' . $results['global_error']);
                return Command::FAILURE;
            }
            
            $io->success('Search index rebuilt successfully!');
            
            $table = $io->createTable();
            $table->setHeaders(['Content Type', 'Indexed', 'Errors']);
            
            foreach (['stories', 'comments', 'users'] as $type) {
                $indexed = $results[$type]['indexed'] ?? 0;
                $errorCount = count($results[$type]['errors'] ?? []);
                $table->addRow([$type, $indexed, $errorCount]);
            }
            
            $table->render();
            
            // Show errors if any
            foreach (['stories', 'comments', 'users'] as $type) {
                if (!empty($results[$type]['errors'])) {
                    $io->warning("Errors in {$type}:");
                    foreach ($results[$type]['errors'] as $error) {
                        $io->text('  - ' . $error);
                    }
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->progressFinish();
            $io->error('Index rebuild failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showStats(SymfonyStyle $io, int $days): int
    {
        $io->section("Search Statistics (Last {$days} days)");
        
        try {
            // Get index stats
            $indexStats = $this->searchIndexService->getIndexStats();
            
            $io->subsection('Index Statistics');
            $io->definitionList(
                ['Total Documents' => number_format($indexStats['total_documents'] ?? 0)],
                ['Stories' => number_format($indexStats['by_type']['story'] ?? 0)],
                ['Comments' => number_format($indexStats['by_type']['comment'] ?? 0)],
                ['Users' => number_format($indexStats['by_type']['user'] ?? 0)],
                ['Index Size' => $this->formatBytes($indexStats['total_size_bytes'] ?? 0)],
                ['Last Updated' => $indexStats['last_updated'] ?? 'Never']
            );
            
            // Get search analytics
            $searchStats = SearchAnalytic::getSearchStats($days);
            
            $io->subsection('Search Analytics');
            $io->definitionList(
                ['Total Searches' => number_format($searchStats['total_searches'])],
                ['Unique Queries' => number_format($searchStats['unique_queries'])],
                ['Avg Results per Search' => $searchStats['avg_results_count']],
                ['Avg Search Time' => $searchStats['avg_search_time_ms'] . 'ms'],
                ['Click-through Rate' => $searchStats['click_through_rate'] . '%'],
                ['No Results Rate' => $searchStats['no_results_rate'] . '%']
            );
            
            // Popular searches
            $popularSearches = SearchAnalytic::getPopularQueries(10, $days);
            if (!empty($popularSearches)) {
                $io->subsection('Popular Searches');
                $table = $io->createTable();
                $table->setHeaders(['Query', 'Search Count']);
                foreach ($popularSearches as $query => $count) {
                    $table->addRow([$query, number_format($count)]);
                }
                $table->render();
            }
            
            // Trending searches
            $trendingSearches = SearchAnalytic::getTrendingQueries(5);
            if (!empty($trendingSearches)) {
                $io->subsection('Trending Searches');
                $table = $io->createTable();
                $table->setHeaders(['Query', 'Trend Ratio']);
                foreach ($trendingSearches as $query => $ratio) {
                    $table->addRow([$query, number_format($ratio, 2) . 'x']);
                }
                $table->render();
            }
            
            // Failed searches
            $failedSearches = SearchAnalytic::getFailedSearches(10, 7);
            if (!empty($failedSearches)) {
                $io->subsection('Top Failed Searches (Last 7 days)');
                $table = $io->createTable();
                $table->setHeaders(['Query', 'Failure Count']);
                foreach ($failedSearches as $query => $count) {
                    $table->addRow([$query, number_format($count)]);
                }
                $table->render();
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Failed to get statistics: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function cleanup(SymfonyStyle $io): int
    {
        $io->section('Cleaning up Search Data');
        
        try {
            $retentionDays = 365; // Could be configurable
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            
            // Clean old analytics
            $io->text('Removing analytics data older than ' . $retentionDays . ' days...');
            $deletedAnalytics = SearchAnalytic::where('created_at', '<', $cutoffDate)->count();
            SearchAnalytic::where('created_at', '<', $cutoffDate)->delete();
            
            $io->success("Cleaned up {$deletedAnalytics} old analytics records");
            
            // Could add more cleanup operations here:
            // - Remove search index entries for deleted content
            // - Optimize search index tables
            // - Clean up cache files
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Cleanup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function bulkIndex(SymfonyStyle $io, string $type, int $offset, int $limit): int
    {
        $io->section("Bulk Indexing: {$type}");
        
        if (!in_array($type, ['stories', 'comments', 'users'])) {
            $io->error('Invalid type. Must be one of: stories, comments, users');
            return Command::INVALID;
        }
        
        try {
            $io->text("Indexing {$type} starting from offset {$offset} with limit {$limit}");
            
            $results = $this->searchIndexService->bulkIndex($type, $offset, $limit);
            
            $io->success("Bulk indexing completed!");
            $io->definitionList(
                ['Indexed' => $results['indexed']],
                ['Total Processed' => $results['total_processed']],
                ['Errors' => count($results['errors'])]
            );
            
            if (!empty($results['errors'])) {
                $io->warning('Errors encountered:');
                foreach ($results['errors'] as $error) {
                    $io->text('  - ' . $error);
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Bulk indexing failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}