-- ============================================================================
--  Reporte Aqui — banco de dados (MySQL 5.7+/8 ou MariaDB 10.4+, padrão do XAMPP)
--  Importe este arquivo pelo phpMyAdmin (aba "Importar") ou pelo terminal:
--      mysql -u root < database.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS reporte_aqui
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reporte_aqui;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS avaliacoes, apoios, notas_internas, historico, fotos,
                     solicitacoes, subcategorias, categorias, setores, usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Usuários (cidadãos e servidores da prefeitura)
-- ----------------------------------------------------------------------------
CREATE TABLE usuarios (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(120) NOT NULL,
  cpf         CHAR(11)     NOT NULL,
  email       VARCHAR(160) NOT NULL,
  telefone    VARCHAR(11)  NOT NULL,
  senha_hash  VARCHAR(255) NOT NULL,
  perfil      ENUM('cidadao','admin') NOT NULL DEFAULT 'cidadao',
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_cpf (cpf),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Setores responsáveis
-- ----------------------------------------------------------------------------
CREATE TABLE setores (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome  VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_setores_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Categorias (tipos de problema), com setor padrão e prazo em dias
-- ----------------------------------------------------------------------------
CREATE TABLE categorias (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(80) NOT NULL,
  icone       VARCHAR(40) NOT NULL,
  setor_id    INT UNSIGNED NOT NULL,
  prazo_dias  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nome (nome),
  KEY idx_categorias_setor (setor_id),
  CONSTRAINT fk_categorias_setor FOREIGN KEY (setor_id) REFERENCES setores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subcategorias (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id  INT UNSIGNED NOT NULL,
  nome          VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_subcategorias_categoria (categoria_id),
  CONSTRAINT fk_subcategorias_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Solicitações
-- ----------------------------------------------------------------------------
CREATE TABLE solicitacoes (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  protocolo        VARCHAR(14)  NOT NULL,
  usuario_id       INT UNSIGNED NOT NULL,
  categoria_id     INT UNSIGNED NOT NULL,
  subcategoria_id  INT UNSIGNED NOT NULL,
  setor_id         INT UNSIGNED NOT NULL,
  situacao         ENUM('recebida','em_analise','em_execucao','concluida') NOT NULL DEFAULT 'recebida',
  prioridade       ENUM('baixa','normal','alta') NOT NULL DEFAULT 'normal',
  descricao        VARCHAR(500) NOT NULL,
  endereco         VARCHAR(160) NOT NULL,
  numero           VARCHAR(60)  NULL,
  bairro           VARCHAR(80)  NOT NULL,
  referencia       VARCHAR(160) NULL,
  latitude         DECIMAL(10,7) NOT NULL,
  longitude        DECIMAL(10,7) NOT NULL,
  sigilo           TINYINT(1) NOT NULL DEFAULT 0,
  previsao         DATE NOT NULL,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluido_em     DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_solicitacoes_protocolo (protocolo),
  KEY idx_solicitacoes_usuario (usuario_id),
  KEY idx_solicitacoes_situacao (situacao, prioridade, criado_em),
  KEY idx_solicitacoes_categoria (categoria_id, situacao),
  KEY idx_solicitacoes_bairro (bairro),
  KEY idx_solicitacoes_criado (criado_em),
  KEY idx_solicitacoes_local (latitude, longitude),
  CONSTRAINT fk_solicitacoes_usuario      FOREIGN KEY (usuario_id)      REFERENCES usuarios (id),
  CONSTRAINT fk_solicitacoes_categoria    FOREIGN KEY (categoria_id)    REFERENCES categorias (id),
  CONSTRAINT fk_solicitacoes_subcategoria FOREIGN KEY (subcategoria_id) REFERENCES subcategorias (id),
  CONSTRAINT fk_solicitacoes_setor        FOREIGN KEY (setor_id)        REFERENCES setores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fotos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitacao_id  INT UNSIGNED NOT NULL,
  arquivo         VARCHAR(60) NOT NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fotos_solicitacao (solicitacao_id),
  CONSTRAINT fk_fotos_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Histórico: toda mudança gera um registro (data, situação, mensagem e autor).
-- visivel_cidadao = 0 é usado para mudanças internas (ex.: prioridade).
-- ----------------------------------------------------------------------------
CREATE TABLE historico (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitacao_id   INT UNSIGNED NOT NULL,
  situacao         ENUM('recebida','em_analise','em_execucao','concluida') NOT NULL,
  mensagem         VARCHAR(600) NOT NULL,
  autor_id         INT UNSIGNED NOT NULL,
  visivel_cidadao  TINYINT(1) NOT NULL DEFAULT 1,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_historico_solicitacao (solicitacao_id, criado_em),
  KEY idx_historico_autor (autor_id),
  CONSTRAINT fk_historico_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes (id) ON DELETE CASCADE,
  CONSTRAINT fk_historico_autor       FOREIGN KEY (autor_id)       REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notas internas: visíveis somente para a prefeitura
CREATE TABLE notas_internas (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitacao_id  INT UNSIGNED NOT NULL,
  autor_id        INT UNSIGNED NOT NULL,
  nota            VARCHAR(1000) NOT NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notas_solicitacao (solicitacao_id, criado_em),
  KEY idx_notas_autor (autor_id),
  CONSTRAINT fk_notas_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes (id) ON DELETE CASCADE,
  CONSTRAINT fk_notas_autor       FOREIGN KEY (autor_id)       REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Apoios: um por cidadão em cada solicitação
CREATE TABLE apoios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitacao_id  INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_apoios (solicitacao_id, usuario_id),
  KEY idx_apoios_usuario (usuario_id),
  CONSTRAINT fk_apoios_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes (id) ON DELETE CASCADE,
  CONSTRAINT fk_apoios_usuario     FOREIGN KEY (usuario_id)     REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Avaliações: uma por solicitação concluída, feita por quem registrou
CREATE TABLE avaliacoes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitacao_id  INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  nota            TINYINT UNSIGNED NOT NULL,
  comentario      VARCHAR(300) NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_avaliacoes_solicitacao (solicitacao_id),
  KEY idx_avaliacoes_usuario (usuario_id),
  CONSTRAINT fk_avaliacoes_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes (id) ON DELETE CASCADE,
  CONSTRAINT fk_avaliacoes_usuario     FOREIGN KEY (usuario_id)     REFERENCES usuarios (id),
  CONSTRAINT ck_avaliacoes_nota CHECK (nota BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  DADOS DE EXEMPLO
-- ============================================================================

-- Senhas: admin1234 (admin) e 12345678 (cidadãos), gravadas com password_hash()
INSERT INTO usuarios (id, nome, cpf, email, telefone, senha_hash, perfil) VALUES
(1, 'Prefeitura de Videira — Atendimento', '11144477735', 'admin@reporteaqui.local', '4935330000',
    '$2y$10$bJB1Ys2oVAtS7EmpKOhX6eA8QKSW3Qh0vbCyEYLXE0y699bbH1WYq', 'admin'),
(2, 'Marcos Antônio Ferreira', '52998224725', 'marcos@email.com', '49999001122',
    '$2y$10$Sx/W.HQdl6U9SvET8Mu05eKYc7DP4Cfwc9hpA4qm8CxZ0h9X8Sk92', 'cidadao'),
(3, 'Ana Paula Ribeiro', '39053344705', 'ana@email.com', '49988776655',
    '$2y$10$Sx/W.HQdl6U9SvET8Mu05eKYc7DP4Cfwc9hpA4qm8CxZ0h9X8Sk92', 'cidadao');

INSERT INTO setores (id, nome) VALUES
(1, 'Secretaria de Obras'),
(2, 'Iluminação Pública'),
(3, 'Limpeza Urbana'),
(4, 'Fiscalização Ambiental'),
(5, 'Departamento de Trânsito');

INSERT INTO categorias (id, nome, icone, setor_id, prazo_dias) VALUES
(1, 'Buraco na via',      'bi-cone-striped',     1, 15),
(2, 'Iluminação pública', 'bi-lightbulb',        2, 7),
(3, 'Lixo acumulado',     'bi-trash3',           3, 5),
(4, 'Entulho irregular',  'bi-bricks',           4, 10),
(5, 'Calçada danificada', 'bi-person-walking',   1, 20),
(6, 'Sinalização',        'bi-sign-stop',        5, 10);

INSERT INTO subcategorias (id, categoria_id, nome) VALUES
( 1, 1, 'Buraco pequeno'),
( 2, 1, 'Buraco grande ou cratera'),
( 3, 1, 'Asfalto afundando'),
( 4, 1, 'Tampa de bueiro danificada'),
( 5, 2, 'Lâmpada apagada'),
( 6, 2, 'Lâmpada piscando'),
( 7, 2, 'Lâmpada acesa durante o dia'),
( 8, 2, 'Poste danificado'),
( 9, 3, 'Coleta não realizada'),
(10, 3, 'Lixo em terreno baldio'),
(11, 3, 'Lixeira pública cheia ou quebrada'),
(12, 4, 'Restos de construção'),
(13, 4, 'Móveis descartados'),
(14, 4, 'Galhos e podas'),
(15, 5, 'Piso quebrado ou solto'),
(16, 5, 'Desnível ou degrau'),
(17, 5, 'Falta de rampa de acessibilidade'),
(18, 5, 'Raiz de árvore levantando o piso'),
(19, 6, 'Placa danificada ou caída'),
(20, 6, 'Faixa de pedestres apagada'),
(21, 6, 'Semáforo com defeito'),
(22, 6, 'Falta de sinalização');

-- As datas são relativas ao dia da importação, para os exemplos ficarem sempre atuais.
SET @ano = YEAR(NOW());

INSERT INTO solicitacoes
(id, protocolo, usuario_id, categoria_id, subcategoria_id, setor_id, situacao, prioridade, descricao,
 endereco, numero, bairro, referencia, latitude, longitude, sigilo, previsao, criado_em, concluido_em) VALUES
(1, CONCAT('RA-', @ano, '-0001'), 2, 1, 2, 1, 'concluida', 'alta',
 'Buraco grande no meio da pista, perto da faixa de pedestres. Os carros desviam para a contramão.',
 'Rua Brasil', '450', 'Centro', 'Em frente à farmácia', -27.0079000, -51.1518000, 0,
 DATE(NOW() - INTERVAL 25 DAY), NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 31 DAY),

(2, CONCAT('RA-', @ano, '-0002'), 3, 2, 5, 2, 'concluida', 'normal',
 'Duas lâmpadas apagadas há mais de uma semana. A rua fica muito escura à noite.',
 'Rua Saul Brandalise', 'próximo ao nº 120', 'Centro', 'Perto da praça', -27.0062000, -51.1541000, 0,
 DATE(NOW() - INTERVAL 21 DAY), NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 23 DAY),

(3, CONCAT('RA-', @ano, '-0003'), 2, 3, 10, 3, 'concluida', 'normal',
 'Terreno baldio com muito lixo acumulado, com mau cheiro e presença de ratos.',
 'Rua Nicolau Cavon', '88', 'Santa Tereza', 'Ao lado do mercado', -27.0121000, -51.1472000, 0,
 DATE(NOW() - INTERVAL 15 DAY), NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 17 DAY),

(4, CONCAT('RA-', @ano, '-0004'), 3, 1, 3, 1, 'em_execucao', 'alta',
 'O asfalto está afundando junto ao bueiro e aumenta a cada chuva. Risco para motociclistas.',
 'Avenida Dom Pedro II', '1200', 'São Cristóvão', 'Esquina com a Rua das Flores', -27.0034000, -51.1489000, 0,
 DATE(NOW() + INTERVAL 3 DAY), NOW() - INTERVAL 12 DAY, NULL),

(5, CONCAT('RA-', @ano, '-0005'), 2, 5, 15, 1, 'em_execucao', 'normal',
 'Calçada com várias lajotas soltas. Uma senhora já caiu no local na semana passada.',
 'Rua XV de Novembro', '312', 'Centro', 'Em frente à escola', -27.0091000, -51.1533000, 0,
 DATE(NOW() + INTERVAL 6 DAY), NOW() - INTERVAL 14 DAY, NULL),

(6, CONCAT('RA-', @ano, '-0006'), 3, 4, 12, 4, 'em_analise', 'normal',
 'Restos de obra despejados na beira da rua, ocupando parte da calçada e da pista.',
 'Rua Padre Anchieta', 'sem número', 'Alvorada', 'Próximo ao campo de futebol', -27.0153000, -51.1562000, 1,
 DATE(NOW() - INTERVAL 2 DAY), NOW() - INTERVAL 12 DAY, NULL),

(7, CONCAT('RA-', @ano, '-0007'), 2, 6, 20, 5, 'em_analise', 'normal',
 'A faixa de pedestres em frente à creche está quase totalmente apagada.',
 'Rua Coronel Schmidt', '75', 'Cidade Alta', 'Em frente à creche municipal', -27.0048000, -51.1576000, 0,
 DATE(NOW() + INTERVAL 5 DAY), NOW() - INTERVAL 5 DAY, NULL),

(8, CONCAT('RA-', @ano, '-0008'), 3, 2, 6, 2, 'recebida', 'normal',
 'Lâmpada do poste piscando a noite inteira, incomoda os moradores e pode queimar.',
 'Rua Marechal Deodoro', '540', 'Farroupilha', NULL, -27.0108000, -51.1438000, 0,
 DATE(NOW() + INTERVAL 5 DAY), NOW() - INTERVAL 2 DAY, NULL),

(9, CONCAT('RA-', @ano, '-0009'), 2, 3, 9, 3, 'recebida', 'normal',
 'O caminhão da coleta não passou nesta semana e os sacos estão acumulados na calçada.',
 'Rua Sete de Setembro', '230', 'Santa Tereza', 'Perto da igreja', -27.0135000, -51.1502000, 0,
 DATE(NOW() + INTERVAL 4 DAY), NOW() - INTERVAL 1 DAY, NULL),

(10, CONCAT('RA-', @ano, '-0010'), 3, 1, 1, 1, 'recebida', 'normal',
 'Buraco pequeno, mas fundo, bem na entrada da rua. Já furou o pneu de um vizinho.',
 'Rua Getúlio Vargas', '18', 'Amarante', 'Entrada do loteamento', -27.0019000, -51.1534000, 1,
 DATE(NOW() + INTERVAL 15 DAY), NOW() - INTERVAL 3 HOUR, NULL);

INSERT INTO historico (solicitacao_id, situacao, mensagem, autor_id, visivel_cidadao, criado_em) VALUES
(1, 'recebida',    'Solicitação registrada pelo morador.', 2, 1, NOW() - INTERVAL 40 DAY),
(1, 'recebida',    'Prioridade alterada de Normal para Alta.', 1, 0, NOW() - INTERVAL 39 DAY),
(1, 'em_analise',  'Recebemos sua solicitação. Uma equipe fará a vistoria do local nos próximos dias.', 1, 1, NOW() - INTERVAL 39 DAY),
(1, 'em_execucao', 'Vistoria realizada. O reparo foi programado e a equipe de pavimentação já está no local.', 1, 1, NOW() - INTERVAL 34 DAY),
(1, 'concluida',   'Buraco fechado e pista liberada. Obrigado por colaborar com a cidade!', 1, 1, NOW() - INTERVAL 31 DAY),

(2, 'recebida',    'Solicitação registrada pelo morador.', 3, 1, NOW() - INTERVAL 28 DAY),
(2, 'em_analise',  'Solicitação encaminhada à equipe de Iluminação Pública.', 1, 1, NOW() - INTERVAL 27 DAY),
(2, 'em_execucao', 'Troca das lâmpadas agendada para esta semana.', 1, 1, NOW() - INTERVAL 25 DAY),
(2, 'concluida',   'As duas lâmpadas foram substituídas por modelos de LED.', 1, 1, NOW() - INTERVAL 23 DAY),

(3, 'recebida',    'Solicitação registrada pelo morador.', 2, 1, NOW() - INTERVAL 20 DAY),
(3, 'em_analise',  'O proprietário do terreno foi notificado e a limpeza da área pública foi programada.', 1, 1, NOW() - INTERVAL 19 DAY),
(3, 'em_execucao', 'Equipe de limpeza no local.', 1, 1, NOW() - INTERVAL 18 DAY),
(3, 'concluida',   'Limpeza concluída. O terreno seguirá sendo fiscalizado.', 1, 1, NOW() - INTERVAL 17 DAY),

(4, 'recebida',    'Solicitação registrada pelo morador.', 3, 1, NOW() - INTERVAL 12 DAY),
(4, 'recebida',    'Prioridade alterada de Normal para Alta.', 1, 0, NOW() - INTERVAL 11 DAY),
(4, 'em_analise',  'Vistoria agendada. O local foi sinalizado com cones.', 1, 1, NOW() - INTERVAL 11 DAY),
(4, 'em_execucao', 'Foi identificado um vazamento na rede de drenagem. O conserto está em andamento.', 1, 1, NOW() - INTERVAL 6 DAY),

(5, 'recebida',    'Solicitação registrada pelo morador.', 2, 1, NOW() - INTERVAL 14 DAY),
(5, 'em_analise',  'Solicitação em análise pela Secretaria de Obras.', 1, 1, NOW() - INTERVAL 12 DAY),
(5, 'em_execucao', 'Material comprado. A reforma do trecho começa nos próximos dias.', 1, 1, NOW() - INTERVAL 4 DAY),

(6, 'recebida',    'Solicitação registrada pelo morador.', 3, 1, NOW() - INTERVAL 12 DAY),
(6, 'em_analise',  'A fiscalização irá ao local para identificar o responsável pelo descarte.', 1, 1, NOW() - INTERVAL 9 DAY),

(7, 'recebida',    'Solicitação registrada pelo morador.', 2, 1, NOW() - INTERVAL 5 DAY),
(7, 'em_analise',  'Pedido incluído na programação de pintura do Departamento de Trânsito.', 1, 1, NOW() - INTERVAL 3 DAY),

(8, 'recebida',    'Solicitação registrada pelo morador.', 3, 1, NOW() - INTERVAL 2 DAY),
(9, 'recebida',    'Solicitação registrada pelo morador.', 2, 1, NOW() - INTERVAL 1 DAY),
(10, 'recebida',   'Solicitação registrada pelo morador.', 3, 1, NOW() - INTERVAL 3 HOUR);

INSERT INTO notas_internas (solicitacao_id, autor_id, nota, criado_em) VALUES
(4, 1, 'Acionar a empresa de saneamento: suspeita de vazamento na galeria.', NOW() - INTERVAL 10 DAY),
(6, 1, 'Vizinhos informaram a placa do caminhão que fez o descarte. Verificar com a fiscalização.', NOW() - INTERVAL 8 DAY);

INSERT INTO apoios (solicitacao_id, usuario_id, criado_em) VALUES
(4, 2, NOW() - INTERVAL 10 DAY),
(5, 3, NOW() - INTERVAL 9 DAY),
(1, 3, NOW() - INTERVAL 38 DAY);

INSERT INTO avaliacoes (solicitacao_id, usuario_id, nota, comentario, criado_em) VALUES
(1, 2, 5, 'Resolveram rápido e o serviço ficou bem feito.', NOW() - INTERVAL 30 DAY),
(2, 3, 4, 'Demorou alguns dias, mas ficou ótimo.', NOW() - INTERVAL 22 DAY);
