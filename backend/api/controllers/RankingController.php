<?php
require_once __DIR__ . '/../services/RankingService.php';

class RankingController {
    private $rankingService;

    public function __construct() {
        $this->rankingService = new RankingService();
    }

    public function showRanking() {
        $rankingData = $this->rankingService->getRanking();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'ranking' => $rankingData
        ]);
    }
}