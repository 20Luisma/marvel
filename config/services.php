<?php

declare(strict_types=1);

return [
    'default_environment' => 'local',
    'environments' => [
        'local' => [
            'app' => [
                'host' => 'localhost:8080',
                'base_url' => 'http://localhost:8080',
            ],
            'openai' => [
                'host' => 'localhost:8081',
                'base_url' => 'http://localhost:8081',
                'chat_url' => 'http://localhost:8081/v1/chat',
            ],
            'rag' => [
                'host' => 'localhost:8082',
                'base_url' => 'http://localhost:8082',
                'heroes_url' => 'http://localhost:8082/rag/heroes',
            ],
        ],
        'hosting' => [
            'app' => [
                'host' => 'iamasterbigschool.contenido.creawebes.com',
                'base_url' => 'https://iamasterbigschool.contenido.creawebes.com',
            ],
            'openai' => [
                'host' => 'openai-service.contenido.creawebes.com',
                'base_url' => 'https://openai-service.contenido.creawebes.com',
                'chat_url' => 'https://openai-service.contenido.creawebes.com/v1/chat',
            ],
            'rag' => [
                'host' => 'rag-service.contenido.creawebes.com',
                'base_url' => 'https://rag-service.contenido.creawebes.com',
                'heroes_url' => 'https://rag-service.contenido.creawebes.com/rag/heroes',
            ],
        ],
        'staging' => [
            'app' => [
                'host' => 'staging.contenido.creawebes.com',
                'base_url' => 'https://staging.contenido.creawebes.com',
            ],
            'openai' => [
                'host' => 'staging.contenido.creawebes.com',
                'base_url' => 'https://staging.contenido.creawebes.com/openai-service',
                'chat_url' => 'https://staging.contenido.creawebes.com/openai-service/v1/chat',
            ],
            'rag' => [
                'host' => 'staging.contenido.creawebes.com',
                'base_url' => 'https://staging.contenido.creawebes.com/rag-service',
                'heroes_url' => 'https://staging.contenido.creawebes.com/rag-service/rag/heroes',
            ],
        ],
    ],
];

