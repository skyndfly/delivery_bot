<?php

namespace api;

use Exception;
use repositories\UserRepository;

class WebhookController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * GET /api/index.php?action=webhook-add-user&id={id}
     */
    public function addUser(): void
    {
        try {
            if(!isset($_GET['id'])){
                http_response_code(400);
                echo json_encode(['error' => 'id is required']);
                throw new Exception('Ошибка сохранения пользователя');
            }

            $userId = (int) $_GET['id'];
            $this->userRepository->addUser($userId);

            echo json_encode(['status' => 'ok', 'userId' => $userId]);
            log_dump('Добавлен пользователь: ' . $userId, 'WebhookController');
        }catch (Exception $exception){
            log_dump($exception->getMessage(), 'WebhookController');
        }
    }
}