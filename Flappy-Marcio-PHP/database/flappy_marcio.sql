CREATE DATABASE IF NOT EXISTS flappy_marcio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE flappy_marcio;

CREATE TABLE IF NOT EXISTS jogadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    nome_chave VARCHAR(50) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    xp INT NOT NULL DEFAULT 0,
    skin VARCHAR(30) NOT NULL DEFAULT 'classica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    pontos INT NOT NULL DEFAULT 0,
    fase INT NOT NULL DEFAULT 1,
    duracao_seg DECIMAL(8,2) NOT NULL DEFAULT 0,
    velocidade_final DECIMAL(5,2) NOT NULL DEFAULT 1,
    dificuldade VARCHAR(20) NOT NULL DEFAULT 'normal',
    skin VARCHAR(30) NOT NULL DEFAULT 'classica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_partidas_data (criado_em),
    INDEX idx_partidas_dificuldade (dificuldade),
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pontuacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    dificuldade VARCHAR(20) NOT NULL DEFAULT 'normal',
    pontos INT NOT NULL DEFAULT 0,
    fase INT NOT NULL DEFAULT 1,
    duracao_seg DECIMAL(8,2) NOT NULL DEFAULT 0,
    velocidade_final DECIMAL(5,2) NOT NULL DEFAULT 1,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY jogador_dificuldade (jogador_id, dificuldade),
    INDEX idx_pontuacoes_ranking (pontos, atualizado_em),
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conquistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    codigo VARCHAR(60) NOT NULL,
    titulo VARCHAR(80) NOT NULL,
    descricao VARCHAR(160) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY jogador_conquista (jogador_id, codigo),
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS missoes_concluidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    codigo VARCHAR(60) NOT NULL,
    titulo VARCHAR(80) NOT NULL,
    data_ref DATE NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY jogador_missao (jogador_id, codigo, data_ref),
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(40) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS partidas_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    token CHAR(48) NOT NULL UNIQUE,
    dificuldade VARCHAR(20) NOT NULL DEFAULT 'normal',
    skin VARCHAR(30) NOT NULL DEFAULT 'classica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usado_em DATETIME NULL,
    INDEX idx_token_jogador (jogador_id, token),
    INDEX idx_token_expiracao (criado_em, usado_em),
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO administradores (usuario, senha_hash) VALUES
('admin', '$2y$12$Pj8f57zxHnvAHTHrOf.xY.XsN1UAcny1ZB51oMgmf0B.1zcsPPqZW');

CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(60) NOT NULL UNIQUE,
    valor VARCHAR(120) NOT NULL,
    descricao VARCHAR(160) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
('velocidade_inicial', '3.85', 'Velocidade inicial dos canos'),
('aumento_fase', '0.34', 'Aumento de velocidade a cada fase'),
('velocidade_maxima', '6.40', 'Limite de velocidade'),
('pontos_por_fase', '6', 'Pontos para mudar de fase'),
('abertura_inicial', '218', 'Espaço inicial entre canos'),
('abertura_minima', '178', 'Menor espaço entre canos'),
('gravidade', '0.46', 'Força da gravidade'),
('pulo', '-8.25', 'Força do pulo');
