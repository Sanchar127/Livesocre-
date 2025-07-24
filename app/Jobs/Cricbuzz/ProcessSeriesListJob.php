<?Php
namespace App\Jobs\Cricbuzz;

use App\Services\CricketDataProcessor;
use App\Jobs\Cricbuzz\ProcessMatchesJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSeriesListJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $seriesList;

    public function __construct(array $seriesList)
    {
        $this->seriesList = $seriesList;
    }

    public function handle(CricketDataProcessor $processor): void
    {
        $processor->processFixtures($this->seriesList, sportId: 1);

        foreach ($this->seriesList as $seriesItem) {
            $seriesId = $seriesItem['Externalseries_id'];
            $seriesSlug = $seriesItem['series_slug'];
            if ($seriesId && $seriesSlug) {
                ProcessMatchesJob::dispatch($seriesId, $seriesSlug)->onQueue('cricbuzz');
            }
        }
    }
}
