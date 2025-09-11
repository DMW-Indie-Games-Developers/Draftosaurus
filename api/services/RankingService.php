<?php
require_once __DIR__ . '/../repositories/RankingRepository.php';

class RankingService {
    private $rankingRepository;

    public function __construct() {
        $this->rankingRepository = new RankingRepository();
    }

    public function getRanking() {
        return $this->rankingRepository->getTopPlayers(10); // Obtener el Top 10
    }
}