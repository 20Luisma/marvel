<?php

declare(strict_types=1);

namespace Tests\Movies;

use App\Movies\Application\RecommendMoviesUseCase;
use App\Movies\Infrastructure\ML\PhpMlMovieRecommender;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Movies\Infrastructure\ML\PhpMlMovieRecommender
 * @covers \App\Movies\Application\RecommendMoviesUseCase
 */
final class MovieRecommenderTest extends TestCase
{
    private RecommendMoviesUseCase $useCase;

    /** @var array<int, array<string, mixed>> */
    private array $catalog;

    protected function setUp(): void
    {
        $recommender = new PhpMlMovieRecommender();
        $this->useCase = new RecommendMoviesUseCase($recommender);

        $this->catalog = [
            [
                'id' => 1,
                'title' => 'Avengers: Endgame',
                'vote_average' => 8.3,
                'release_date' => '2019-04-24',
                'overview' => 'Los Vengadores restantes deben encontrar una manera de recuperar a sus aliados para una batalla épica contra Thanos que pondrá fin a la saga del infinito.',
            ],
            [
                'id' => 2,
                'title' => 'Avengers: Infinity War',
                'vote_average' => 8.2,
                'release_date' => '2018-04-25',
                'overview' => 'Los Vengadores y sus aliados deben estar dispuestos a sacrificarlo todo en un intento de derrotar al poderoso Thanos en una batalla épica por el destino del universo.',
            ],
            [
                'id' => 3,
                'title' => 'Capitán América: Civil War',
                'vote_average' => 7.4,
                'release_date' => '2016-04-27',
                'overview' => 'Los Vengadores se dividen en dos bandos liderados por Steve Rogers y Tony Stark en una batalla que cambiará el rumbo del universo cinematográfico Marvel.',
            ],
            [
                'id' => 4,
                'title' => 'Guardianes de la Galaxia',
                'vote_average' => 7.9,
                'release_date' => '2014-07-30',
                'overview' => 'Un grupo de inadaptados intergalácticos deben unir fuerzas para proteger una poderosa gema que podría destruir el cosmos.',
            ],
            [
                'id' => 5,
                'title' => 'Ant-Man',
                'vote_average' => 7.1,
                'release_date' => '2015-07-14',
                'overview' => 'Un ladrón experto debe ayudar a su mentor a proteger el secreto de un increíble traje que permite reducir de tamaño y aumentar la fuerza.',
            ],
            [
                'id' => 6,
                'title' => 'Thor: Ragnarok',
                'vote_average' => 7.6,
                'release_date' => '2017-10-10',
                'overview' => 'Thor debe sobrevivir a una carrera de gladiadores y encontrar la manera de volver a Asgard para detener a Hela y evitar el Ragnarok.',
            ],
            [
                'id' => 7,
                'title' => 'Iron Man',
                'vote_average' => 7.6,
                'release_date' => '2008-04-30',
                'overview' => 'Tony Stark construye una armadura de alta tecnología para escapar del cautiverio y se convierte en el héroe conocido como Iron Man.',
            ],
        ];
    }

    public function testRecommendsMoviesSuccessfully(): void
    {
        $result = $this->useCase->execute(1, $this->catalog, 3);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['target']['id']);
        $this->assertSame('Avengers: Endgame', $result['target']['title']);
        $this->assertCount(3, $result['recommendations']);
    }

    public function testInfinityWarIsMostSimilarToEndgame(): void
    {
        $result = $this->useCase->execute(1, $this->catalog, 3);

        $this->assertTrue($result['ok']);

        // Infinity War should be the top recommendation for Endgame
        // (most similar vote, closest year, most similar overview)
        $topRecommendation = $result['recommendations'][0];
        $this->assertSame(2, $topRecommendation['id']);
        $this->assertSame('Avengers: Infinity War', $topRecommendation['title']);
    }

    public function testSimilarityScoreIsPercentage(): void
    {
        $result = $this->useCase->execute(1, $this->catalog, 5);

        foreach ($result['recommendations'] as $rec) {
            $this->assertArrayHasKey('similarity_score', $rec);
            $this->assertGreaterThanOrEqual(0, $rec['similarity_score']);
            $this->assertLessThanOrEqual(100, $rec['similarity_score']);
        }
    }

    public function testRecommendationsExcludeTargetMovie(): void
    {
        $result = $this->useCase->execute(1, $this->catalog, 10);

        foreach ($result['recommendations'] as $rec) {
            $this->assertNotSame(1, $rec['id'], 'Target movie should not appear in recommendations');
        }
    }

    public function testMovieNotFoundReturnsError(): void
    {
        $result = $this->useCase->execute(999, $this->catalog, 3);

        $this->assertFalse($result['ok']);
        $this->assertSame('Movie not found in catalog', $result['error']);
        $this->assertEmpty($result['recommendations']);
    }

    public function testLimitRespectsMaximum(): void
    {
        $result = $this->useCase->execute(1, $this->catalog, 2);

        $this->assertCount(2, $result['recommendations']);
    }

    public function testRecommendationsAreSortedBySimilarity(): void
    {
        $result = $this->useCase->execute(1, $this->catalog, 5);

        $scores = array_column($result['recommendations'], 'similarity_score');
        $sorted = $scores;
        rsort($sorted);

        $this->assertSame($sorted, $scores, 'Recommendations should be sorted by similarity descending');
    }

    public function testRecommendationContainsRequiredFields(): void
    {
        $result = $this->useCase->execute(1, $this->catalog, 1);

        $rec = $result['recommendations'][0];
        $this->assertArrayHasKey('id', $rec);
        $this->assertArrayHasKey('title', $rec);
        $this->assertArrayHasKey('vote_average', $rec);
        $this->assertArrayHasKey('similarity_score', $rec);
        $this->assertArrayHasKey('text_similarity', $rec);
    }

    public function testTextSimilarityIsPercentage(): void
    {
        $result = $this->useCase->execute(1, $this->catalog, 5);

        foreach ($result['recommendations'] as $rec) {
            $this->assertArrayHasKey('text_similarity', $rec);
            $this->assertGreaterThanOrEqual(0, $rec['text_similarity']);
            $this->assertLessThanOrEqual(100, $rec['text_similarity']);
        }
    }

    public function testEmptyCatalogReturnsNotFound(): void
    {
        $result = $this->useCase->execute(1, [], 3);

        $this->assertFalse($result['ok']);
    }

    public function testSingleMovieCatalogReturnsEmptyRecommendations(): void
    {
        $catalog = [
            [
                'id' => 1,
                'title' => 'Avengers: Endgame',
                'vote_average' => 8.3,
                'release_date' => '2019-04-24',
                'overview' => 'Los Vengadores restantes.',
            ],
        ];

        $result = $this->useCase->execute(1, $catalog, 3);

        $this->assertTrue($result['ok']);
        $this->assertEmpty($result['recommendations']);
    }

    public function testHandlesEmptyOverviewsAndMissingData(): void
    {
        $catalog = [
            ['id' => 1, 'title' => 'Target', 'overview' => ''],
            ['id' => 2, 'title' => 'Empty', 'overview' => ''],
            ['id' => 3, 'title' => 'Only Stop Words', 'overview' => 'el la los'],
            ['id' => 4, 'title' => 'No Year', 'vote_average' => 5.0],
        ];

        $result = $this->useCase->execute(1, $catalog, 5);

        $this->assertTrue($result['ok']);
        $this->assertCount(3, $result['recommendations']);
        foreach ($result['recommendations'] as $rec) {
            $this->assertIsFloat($rec['similarity_score']);
        }
    }
}
