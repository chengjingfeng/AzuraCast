<?php
namespace App\Controller\Api\Stations;

use App;
use Azura\Doctrine\Paginator;
use Azura\Utilities\Csv;
use Doctrine\ORM\EntityManager;
use App\Entity;
use App\Http\Request;
use App\Http\Response;
use Psr\Http\Message\ResponseInterface;
use OpenApi\Annotations as OA;

class HistoryController
{
    /** @var EntityManager */
    protected $em;

    /** @var App\ApiUtilities */
    protected $api_utils;

    /**
     * @param EntityManager $em
     * @param App\ApiUtilities $api_utils
     *
     * @see App\Controller\Api\ApiProvider
     */
    public function __construct(EntityManager $em, App\ApiUtilities $api_utils)
    {
        $this->em = $em;
        $this->api_utils = $api_utils;
    }

    /**
     * @OA\Get(path="/station/{station_id}/history",
     *   tags={"Stations: History"},
     *   description="Return song playback history items for a given station.",
     *   @OA\Parameter(ref="#/components/parameters/station_id_required"),
     *   @OA\Parameter(
     *     name="start",
     *     description="The start date for records, in YYYY-MM-DD format.",
     *     in="query",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="end",
     *     description="The end date for records, in YYYY-MM-DD format.",
     *     in="query",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Success",
     *     @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Api_DetailedSongHistory"))
     *   ),
     *   @OA\Response(response=404, description="Station not found"),
     *   @OA\Response(response=403, description="Access denied"),
     *   security={{"api_key": {}}},
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param int|string $station_id
     * @return ResponseInterface
     */
    public function __invoke(Request $request, Response $response, $station_id): ResponseInterface
    {
        $station = $request->getStation();

        $start_param = $request->getParam('start');
        if (!empty($start_param)) {
            $start = strtotime($start_param . ' 00:00:00');
            $end = strtotime($request->getParam('end', $start_param) . ' 23:59:59');
        } else {
            $start = strtotime('-2 weeks');
            $end = time();
        }

        $qb = $this->em->createQueryBuilder();

        $qb->select('sh, sr, sp, s')
            ->from(Entity\SongHistory::class, 'sh')
            ->leftJoin('sh.request', 'sr')
            ->leftJoin('sh.playlist', 'sp')
            ->leftJoin('sh.song', 's')
            ->where('sh.station_id = :station_id')
            ->andWhere('sh.timestamp_start >= :start AND sh.timestamp_start <= :end')
            ->andWhere('sh.listeners_start IS NOT NULL')
            ->setParameter('station_id', $station_id)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($request->getParam('format', 'json') === 'csv') {
            $export_all = [];
            $export_all[] = [
                'Date',
                'Time',
                'Listeners',
                'Delta',
                'Likes',
                'Dislikes',
                'Track',
                'Artist',
                'Playlist'
            ];

            foreach ($qb->getQuery()->getArrayResult() as $song_row) {
                $export_row = [
                    date('Y-m-d', $song_row['timestamp_start']),
                    date('g:ia', $song_row['timestamp_start']),
                    $song_row['listeners_start'],
                    $song_row['delta_total'],
                    $song_row['score_likes'],
                    $song_row['score_dislikes'],
                    $song_row['song']['title'] ?: $song_row['song']['text'],
                    $song_row['song']['artist'],
                    $song_row['playlist']['name'] ?? '',
                ];

                $export_all[] = $export_row;
            }

            $csv_file = Csv::arrayToCsv($export_all);
            $csv_filename = $station->getShortName() . '_timeline_' . date('Ymd', $start) . '_to_'. date('Ymd', $end).'.csv';

            return $response->renderStringAsFile($csv_file, 'text/csv', $csv_filename);
        }

        $search_phrase = trim($request->getParam('searchPhrase'));
        if (!empty($search_phrase)) {
            $qb->andWhere('(s.title LIKE :query OR s.artist LIKE :query)')
                ->setParameter('query', '%'.$search_phrase.'%');
        }

        $qb->orderBy('sh.timestamp_start', 'DESC');

        $paginator = new Paginator($qb);
        $paginator->setFromRequest($request);

        $is_bootgrid = $paginator->isFromBootgrid();
        $router = $request->getRouter();

        $paginator->setPostprocessor(function($sh_row) use ($is_bootgrid, $router) {

            /** @var Entity\SongHistory $sh_row */
            $row = $sh_row->api(new Entity\Api\DetailedSongHistory, $this->api_utils);
            $row->resolveUrls($router->getBaseUrl());

            if ($is_bootgrid) {
                return App\Utilities::flattenArray($row, '_');
            }

            return $row;
        });

        return $paginator->write($response);
    }
}
