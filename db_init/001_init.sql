-- =================================================================================================
-- SCRIPT COMPLETO E UNIFICADO DE INSTALAÇÃO E POPULAÇÃO DO BANCO DE DADOS
-- Este arquivo único cria, define e popula todo o banco de dados em seu estado final.
-- VERSÃO CORRIGIDA E APRIMORADA COM MAIS DADOS ALEATÓRIOS
-- =================================================================================================

-- --- SEÇÃO 1: CRIAÇÃO DO BANCO DE DADOS ---
DROP DATABASE IF EXISTS lost_and_found_db;
CREATE DATABASE lost_and_found_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lost_and_found_db;

-- --- SEÇÃO 2: DEFINIÇÃO DA ESTRUTURA (SCHEMA) ---

-- Tabela de Configurações
CREATE TABLE `settings` (
    `config_id` INT PRIMARY KEY DEFAULT 1,
    `unidade_nome` VARCHAR(255),
    `cnpj` VARCHAR(20),
    `endereco_rua` VARCHAR(255),
    `endereco_numero` VARCHAR(50),
    `endereco_bairro` VARCHAR(100),
    `endereco_cidade` VARCHAR(100),
    `endereco_estado` VARCHAR(50),
    `endereco_cep` VARCHAR(10),
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT check_single_row CHECK (config_id = 1)
);

-- Tabela de Usuários
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255),
    `role` ENUM('common', 'admin', 'superAdmin', 'admin-aprovador') NOT NULL DEFAULT 'common'
);

-- Tabela de Categorias
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `code` VARCHAR(10) NOT NULL UNIQUE
);

-- Tabela de Locais
CREATE TABLE `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE
);

-- Tabela de Itens
CREATE TABLE `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `category_id` INT NOT NULL,
    `location_id` INT NOT NULL,
    `found_date` DATE NOT NULL,
    `description` TEXT,
    `barcode` VARCHAR(255) UNIQUE,
    `user_id` INT,
    `status` ENUM('Pendente', 'Devolvido', 'Doado', 'Aguardando Aprovação', 'Perdido') NOT NULL DEFAULT 'Pendente',
    `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`),
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Tabela de Empresas/Instituições
CREATE TABLE `companies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `cnpj` VARCHAR(20) DEFAULT NULL UNIQUE,
    `ie` VARCHAR(20) DEFAULT NULL,
    `responsible_name` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address_street` VARCHAR(255) DEFAULT NULL,
    `address_number` VARCHAR(50) DEFAULT NULL,
    `address_complement` VARCHAR(100) DEFAULT NULL,
    `address_neighborhood` VARCHAR(100) DEFAULT NULL,
    `address_city` VARCHAR(100) DEFAULT NULL,
    `address_state` VARCHAR(50) DEFAULT NULL,
    `address_cep` VARCHAR(10) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `observations` TEXT DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_companies_name` (`name`),
    INDEX `idx_companies_cnpj` (`cnpj`),
    INDEX `idx_companies_status` (`status`)
);

-- Tabela de Termos de Doação
CREATE TABLE `donation_terms` (
    `term_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `responsible_donation` VARCHAR(255) NOT NULL,
    `donation_date` DATE NOT NULL,
    `donation_time` TIME NOT NULL,
    `company_id` INT DEFAULT NULL,
    `institution_name` VARCHAR(255) DEFAULT NULL,
    `institution_cnpj` VARCHAR(20),
    `institution_ie` VARCHAR(50),
    `institution_responsible_name` VARCHAR(255) DEFAULT NULL,
    `institution_phone` VARCHAR(30),
    `institution_address_street` VARCHAR(255),
    `institution_address_number` VARCHAR(30),
    `institution_address_bairro` VARCHAR(100),
    `institution_address_cidade` VARCHAR(100),
    `institution_address_estado` VARCHAR(2),
    `institution_address_cep` VARCHAR(15),
    `signature_image_path` VARCHAR(255) NOT NULL,
    `status` VARCHAR(30) NOT NULL,
    `reproval_reason` TEXT,
    `approved_at` TIMESTAMP NULL,
    `approved_by_user_id` INT,
    `reproved_at` TIMESTAMP NULL,
    `reproved_by_user_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_donation_terms_company_id` (`company_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`reproved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Tabela de Itens do Termo de Doação
CREATE TABLE `donation_term_items` (
    `term_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `term_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    FOREIGN KEY (`term_id`) REFERENCES `donation_terms`(`term_id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE RESTRICT
);

-- Tabela de Documentos de Devolução
CREATE TABLE `devolution_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL UNIQUE, -- Garantir que cada item só pode ser devolvido uma vez
    `returned_by_user_id` INT NOT NULL,
    `devolution_timestamp` DATETIME NOT NULL,
    `owner_name` VARCHAR(255) NOT NULL,
    `owner_address` TEXT,
    `owner_phone` VARCHAR(50),
    `owner_credential_number` VARCHAR(100),
    `signature_image_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`),
    FOREIGN KEY (`returned_by_user_id`) REFERENCES `users`(`id`)
);

-- --- SEÇÃO 3: DADOS INICIAIS E DE CONFIGURAÇÃO ---
INSERT INTO `settings` (`config_id`, `unidade_nome`, `cnpj`, `endereco_rua`, `endereco_numero`, `endereco_bairro`, `endereco_cidade`, `endereco_estado`, `endereco_cep`) VALUES
(1, 'Sesc', '12.345.678/0001-99', 'Rua Vergueiro', '1000', 'Paraíso', 'São Paulo', 'SP', '01504-000');

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`) VALUES
(1, 'admin', '$2y$10$DHK9TkhqOrEiZfAl8mqEVeBHUoUt7xDSy.ocHCrYud6.kbYOjJeyK', 'Administrador Padrão', 'superAdmin'),
(2, 'aprovador', '$2y$10$DHK9TkhqOrEiZfAl8mqEVeBHUoUt7xDSy.ocHCrYud6.kbYOjJeyK', 'Aprovador de Doações', 'admin-aprovador'),
(3, 'comum', '$2y$10$DHK9TkhqOrEiZfAl8mqEVeBHUoUt7xDSy.ocHCrYud6.kbYOjJeyK', 'Usuário Comum', 'common');


INSERT INTO `categories` (`id`, `name`, `code`) VALUES
(1, 'Roupa', 'ROP'), (2, 'Medicamento', 'MED'), (3, 'Acessórios', 'ACS'), (4, 'Eletrônicos', 'ELE'), (5, 'Documentos', 'DOC'), (6, 'Outros', 'OUT'),
(7, 'Livros', 'LIV'), (8, 'Brinquedos', 'BRN'), (9, 'Garrafas e Copos', 'GCO'), (10, 'Chaves', 'CHA'), (11, 'Óculos', 'OCL'), (12, 'Material de Escritório', 'ESC'),
(13, 'Equipamento Esportivo', 'ESP'), (14, 'Bolsas e Mochilas', 'BOL');

INSERT INTO `locations` (`id`, `name`) VALUES
(1, 'Teatro Paulo Autran'), (2, 'Auditório'), (3, 'Espaço de Brincar'), (4, 'Biblioteca'), (5, 'Hall de Entrada'), (6, 'Cafeteria'), (7, 'Sala de Cinema 1'),
(8, 'Sala de Cinema 2'), (9, 'Banheiro Masculino - Térreo'), (10, 'Banheiro Feminino - 1º Andar'), (11, 'Jardim Suspenso');

INSERT INTO `companies` (`id`, `name`, `cnpj`, `responsible_name`, `phone`, `email`, `status`) VALUES
(1, 'Exército da Salvação', '43.388.922/0001-60', 'Maria Oliveira', '(11) 5591-7070', 'contato@exercitodoacoes.org.br', 'active'),
(2, 'Cruz Vermelha - SP', '59.423.777/0001-51', 'Pedro Souza', '(11) 5056-8686', 'doacoes@cruzvermelhasp.org.br', 'active'),
(3, 'Orfanato Santa Rita', '11.222.333/0001-44', 'Irmã Lúcia', '(11) 2978-5544', 'contato@santarita.org.br', 'active'),
(4, 'Associação de Amigos do Bairro', '88.777.666/0001-55', 'Carlos Lima', '(11) 98765-4321', 'amigos@bairrofeliz.com', 'active');


-- --- SEÇÃO 4: POPULAÇÃO DE DADOS BÁSICA ---

-- Lote 1: Itens com status 'Pendente' (Total: 15)
INSERT INTO `items` (`name`, `category_id`, `location_id`, `found_date`, `description`, `barcode`, `user_id`, `status`) VALUES
('Casaco de couro preto', 1, 1, '2025-04-15', 'Casaco masculino, tamanho M, com zíper.', 'LT-20250001', 1, 'Pendente'),
('Livro "Cem Anos de Solidão"', 7, 2, '2025-04-16', 'Edição de capa dura, em bom estado.', 'LT-20250002', 1, 'Pendente'),
('Garrafa de água de metal rosa', 9, 3, '2025-04-18', 'Garrafa térmica com alguns arranhões.', 'LT-20250003', 1, 'Pendente'),
('Mochila Jansport azul', 14, 4, '2025-04-20', 'Continha um caderno e um estojo.', 'LT-20250004', 1, 'Pendente'),
('Óculos de grau com armação preta', 11, 5, '2025-04-22', 'Armação de plástico, marca Ray-Ban.', 'LT-20250005', 1, 'Pendente'),
('Fone de ouvido Bluetooth Sony', 4, 6, '2025-05-01', 'Modelo WH-1000XM4, na cor preta.', 'LT-20250006', 1, 'Pendente'),
('Chave de carro com chaveiro do Mickey', 10, 7, '2025-05-05', 'Chave da marca Volkswagen.', 'LT-20250007', 1, 'Pendente'),
('Tablet Samsung Galaxy Tab S7', 4, 8, '2025-05-10', 'Tela com pequena rachadura no canto.', 'LT-20250008', 1, 'Pendente'),
('Cachecol de lã xadrez', 1, 1, '2025-05-12', 'Cores predominantes: vermelho e verde.', 'LT-20250009', 1, 'Pendente'),
('Carteira de couro marrom', 3, 2, '2025-05-15', 'Sem documentos, apenas R$ 25,00 em dinheiro.', 'LT-20250010', 1, 'Pendente'),
('Carregador de notebook Dell', 4, 4, '2025-05-20', 'Ponta fina, 65W.', 'LT-20250011', 1, 'Pendente'),
('Anel de prata com pedra azul', 3, 10, '2025-05-22', 'Parece ser topázio.', 'LT-20250012', 1, 'Pendente'),
('Caderno universitário Tilibra', 12, 9, '2025-06-01', 'Capa com estampa de galáxia.', 'LT-20250013', 1, 'Pendente'),
('Mouse sem fio Logitech', 4, 2, '2025-06-05', 'Modelo M185, cor cinza.', 'LT-20250014', 1, 'Pendente'),
('Boné da Nike preto', 3, 8, '2025-06-10', 'Com o logo branco bordado.', 'LT-20250015', 1, 'Pendente');

-- Lote 2: Itens com status 'Devolvido' (Total: 10)
INSERT INTO `items` (`name`, `category_id`, `location_id`, `found_date`, `description`, `barcode`, `user_id`, `status`) VALUES
('iPhone 13 Pro', 4, 1, '2025-03-10', 'Cor grafite, 128GB, com capa transparente.', 'LT-20250151', 1, 'Devolvido'),
('Aliança de ouro', 3, 2, '2025-03-12', 'Com a inscrição "Maria & João 10/05/2020".', 'LT-20250152', 1, 'Devolvido'),
('Passaporte brasileiro', 5, 3, '2025-03-15', 'Em nome de Carlos Alberto de Souza.', 'LT-20250153', 1, 'Devolvido'),
('Notebook Dell Vostro', 4, 4, '2025-04-01', 'Cor cinza, com adesivo da NASA na tampa.', 'LT-20250154', 1, 'Devolvido'),
('Relógio de pulso Casio', 3, 5, '2025-04-05', 'Modelo G-Shock, cor preta.', 'LT-20250155', 1, 'Devolvido'),
('Câmera Canon T5i', 4, 7, '2025-04-10', 'Com lente 18-55mm.', 'LT-20250156', 1, 'Devolvido'),
('Bolsa de Couro Arezzo', 14, 10, '2025-04-11', 'Cor caramelo, com alça de ombro.', 'LT-20250157', 1, 'Devolvido'),
('CNH - Carteira de Motorista', 5, 5, '2025-04-12', 'Em nome de Juliana Ferreira.', 'LT-20250158', 1, 'Devolvido'),
('Kindle Paperwhite', 4, 4, '2025-05-02', 'Com capa roxa.', 'LT-20250159', 1, 'Devolvido'),
('Pulseira Pandora', 3, 9, '2025-05-03', 'Com 3 berloques.', 'LT-20250160', 1, 'Devolvido');


-- Lote 3: Itens com status 'Doado' (Total: 10)
INSERT INTO `items` (`name`, `category_id`, `location_id`, `found_date`, `description`, `barcode`, `user_id`, `status`) VALUES
('Agasalho de moletom cinza', 1, 1, '2024-10-05', 'Tamanho G, com capuz.', 'LT-20250221', 1, 'Doado'),
('Tênis de corrida Nike', 1, 8, '2024-10-10', 'Tamanho 42, modelo Revolution.', 'LT-20250222', 1, 'Doado'),
('Par de luvas de lã', 1, 6, '2024-10-15', 'Cor azul marinho.', 'LT-20250223', 1, 'Doado'),
('Calculadora científica HP', 4, 2, '2024-11-20', 'Modelo 50g.', 'LT-20250224', 1, 'Doado'),
('Caixa de som JBL Go', 4, 3, '2024-11-25', 'Cor azul, funcionando.', 'LT-20250225', 1, 'Doado'),
('Livro "O Pequeno Príncipe"', 7, 4, '2024-12-01', 'Capa dura, edição comemorativa.', 'LT-20250226', 1, 'Doado'),
('Jaqueta Jeans Levis', 1, 1, '2024-12-05', 'Tamanho M, com alguns rasgos de fábrica.', 'LT-20250227', 1, 'Doado'),
('Brinquedo Lego Classic', 8, 3, '2024-12-10', 'Caixa com cerca de 200 peças.', 'LT-20250228', 1, 'Doado'),
('Chapéu de sol de palha', 3, 11, '2024-12-15', 'Com fita preta.', 'LT-20250229', 1, 'Doado'),
('Cinto de couro', 3, 9, '2024-12-20', 'Cor preta, fivela prateada.', 'LT-20250230', 1, 'Doado');

-- CRIAR DOCUMENTOS DE DEVOLUÇÃO PARA O LOTE BÁSICO
INSERT INTO `devolution_documents` (`item_id`, `returned_by_user_id`, `devolution_timestamp`, `owner_name`, `owner_credential_number`, `signature_image_path`)
SELECT id, 1, DATE_ADD(registered_at, INTERVAL 20 DAY), CONCAT('Dono Fictício do Item ', id), '123.456.789-00', '/signatures/placeholder.png'
FROM `items` WHERE `status` = 'Devolvido';

-- CRIAR TERMOS DE DOAÇÃO E VINCULAR ITENS PARA O LOTE BÁSICO
INSERT INTO `donation_terms` (`user_id`, `responsible_donation`, `donation_date`, `donation_time`, `company_id`, `institution_name`, `status`, `approved_at`, `approved_by_user_id`, `signature_image_path`) VALUES 
(1, 'João da Silva', '2025-02-20', '14:30:00', 1, 'Exército da Salvação', 'Doado', '2025-02-19 10:00:00', 2, '/signatures/donation_term_1.png');

INSERT INTO `donation_term_items` (`term_id`, `item_id`)
SELECT 1, id FROM `items` WHERE `status` = 'Doado';


-- --- SEÇÃO 5: POPULAÇÃO ADICIONAL DE DADOS ALEATÓRIOS ---
-- O objetivo desta seção é adicionar uma grande quantidade de dados para simular um ambiente de produção.
-- VERSÃO CORRIGIDA

-- Adicionar 100 itens aleatórios com status 'Pendente'
INSERT INTO `items` (`name`, `category_id`, `location_id`, `found_date`, `description`, `barcode`, `user_id`, `status`)
SELECT
    CONCAT('Item Pendente Aleatório #', i.id + 1000),
    FLOOR(1 + (RAND() * 14)),
    FLOOR(1 + (RAND() * 11)),
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 90) DAY),
    CONCAT('Descrição gerada aleatoriamente para o item pendente. Detalhes: ', UUID()),
    CONCAT('BC-PEND-', UUID()),
    1,
    'Pendente'
FROM `items` i -- <<< CORREÇÃO APLICADA AQUI
LIMIT 100;

-- Adicionar 50 itens aleatórios com status 'Devolvido'
INSERT INTO `items` (`name`, `category_id`, `location_id`, `found_date`, `description`, `barcode`, `user_id`, `status`)
SELECT
    CONCAT('Item Devolvido Aleatório #', i.id + 2000),
    FLOOR(1 + (RAND() * 14)),
    FLOOR(1 + (RAND() * 11)),
    DATE_SUB(NOW(), INTERVAL (90 + FLOOR(RAND() * 90)) DAY), -- Achados entre 3 a 6 meses atrás
    CONCAT('Descrição gerada aleatoriamente para o item devolvido. Detalhes: ', UUID()),
    CONCAT('BC-DEV-', UUID()),
    1,
    'Devolvido'
FROM `items` i -- <<< CORREÇÃO APLICADA AQUI
LIMIT 50;

-- Adicionar 150 itens aleatórios com status 'Doado'
INSERT INTO `items` (`name`, `category_id`, `location_id`, `found_date`, `description`, `barcode`, `user_id`, `status`)
SELECT
    CONCAT('Item Doado Aleatório #', i.id + 3000),
    FLOOR(1 + (RAND() * 14)),
    FLOOR(1 + (RAND() * 11)),
    DATE_SUB(NOW(), INTERVAL (180 + FLOOR(RAND() * 180)) DAY), -- Achados há mais de 6 meses
    CONCAT('Descrição gerada aleatoriamente para o item doado. Detalhes: ', UUID()),
    CONCAT('BC-DOADO-', UUID()),
    1,
    'Doado'
FROM `items` i -- <<< CORREÇÃO APLICADA AQUI
LIMIT 150;


-- ADICIONAR MAIS 50 TERMOS DE DEVOLUÇÃO ALEATÓRIOS
-- Este bloco cria documentos para todos os itens 'Devolvido' que ainda não têm um.
INSERT INTO `devolution_documents` (`item_id`, `returned_by_user_id`, `devolution_timestamp`, `owner_name`, `owner_credential_number`, `signature_image_path`)
SELECT
    i.id,
    1 AS returned_by_user_id,
    DATE_ADD(i.registered_at, INTERVAL FLOOR(5 + RAND() * 25) DAY) AS devolution_timestamp,
    CONCAT('Proprietário Aleatório ', FLOOR(RAND() * 1000)) AS owner_name,
    CONCAT(FLOOR(RAND() * 999), '.', FLOOR(RAND() * 999), '.', FLOOR(RAND() * 999), '-', FLOOR(RAND() * 99)) AS owner_credential_number,
    CONCAT('/signatures/devolution_auto_', i.id, '.png') AS signature_image_path
FROM `items` i
LEFT JOIN `devolution_documents` dd ON i.id = dd.item_id
WHERE i.status = 'Devolvido' AND dd.id IS NULL;


-- ADICIONAR MAIS 35 TERMOS DE DOAÇÃO ALEATÓRIOS
INSERT INTO `donation_terms` 
    (`user_id`, `responsible_donation`, `donation_date`, `donation_time`, `company_id`, `institution_name`, `status`, `approved_at`, `approved_by_user_id`, `signature_image_path`, `institution_cnpj`, `institution_responsible_name`)
VALUES
    (1, 'Ana Clara', '2025-01-10', '10:00:00', 2, 'Cruz Vermelha - SP', 'Doado', '2025-01-09 11:00:00', 2, '/signatures/donation_auto_2.png', '59.423.777/0001-51', 'Pedro Souza'),
    (1, 'Bruno Costa', '2025-01-25', '11:30:00', 3, 'Orfanato Santa Rita', 'Doado', '2025-01-24 12:00:00', 2, '/signatures/donation_auto_3.png', '11.222.333/0001-44', 'Irmã Lúcia'),
    (1, 'Daniel Martins', '2025-02-15', '14:00:00', 4, 'Associação de Amigos do Bairro', 'Doado', '2025-02-14 15:00:00', 2, '/signatures/donation_auto_4.png', '88.777.666/0001-55', 'Carlos Lima'),
    (1, 'Eduarda Lima', '2025-03-05', '09:45:00', 1, 'Exército da Salvação', 'Doado', '2025-03-04 10:00:00', 2, '/signatures/donation_auto_5.png', '43.388.922/0001-60', 'Maria Oliveira'),
    (1, 'Felipe Alves', '2025-03-20', '16:00:00', 2, 'Cruz Vermelha - SP', 'Doado', '2025-03-19 17:00:00', 2, '/signatures/donation_auto_6.png', '59.423.777/0001-51', 'Pedro Souza'),
    (1, 'Gabriela Rocha', '2025-04-12', '13:15:00', 3, 'Orfanato Santa Rita', 'Doado', '2025-04-11 14:00:00', 2, '/signatures/donation_auto_7.png', '11.222.333/0001-44', 'Irmã Lúcia'),
    (1, 'Heitor Barros', '2025-04-28', '11:00:00', 4, 'Associação de Amigos do Bairro', 'Doado', '2025-04-27 12:00:00', 2, '/signatures/donation_auto_8.png', '88.777.666/0001-55', 'Carlos Lima'),
    (1, 'Isabela Neves', '2025-05-15', '10:30:00', 1, 'Exército da Salvação', 'Doado', '2025-05-14 11:00:00', 2, '/signatures/donation_auto_9.png', '43.388.922/0001-60', 'Maria Oliveira'),
    (1, 'João Pedro', '2025-05-30', '15:00:00', 2, 'Cruz Vermelha - SP', 'Doado', '2025-05-29 16:00:00', 2, '/signatures/donation_auto_10.png', '59.423.777/0001-51', 'Pedro Souza'),
    (1, 'Karina Andrade', '2025-06-10', '12:00:00', 3, 'Orfanato Santa Rita', 'Doado', '2025-06-09 13:00:00', 2, '/signatures/donation_auto_11.png', '11.222.333/0001-44', 'Irmã Lúcia'),
    (1, 'Lucas Mendes', '2025-06-25', '09:00:00', 4, 'Associação de Amigos do Bairro', 'Doado', '2025-06-24 10:00:00', 2, '/signatures/donation_auto_12.png', '88.777.666/0001-55', 'Carlos Lima'),
    (1, 'Manuela Dias', '2025-07-01', '14:30:00', 1, 'Exército da Salvação', 'Doado', '2025-06-30 15:00:00', 2, '/signatures/donation_auto_13.png', '43.388.922/0001-60', 'Maria Oliveira'),
    (1, 'Responsável Aleatório A', '2024-01-15', '09:30:00', 1, 'Instituição A', 'Doado', '2024-01-14 10:00:00', 2, '/signatures/donation_auto_14.png', '11.111.111/0001-11', 'Contato A'),
    (1, 'Responsável Aleatório B', '2024-02-01', '14:00:00', 2, 'Instituição B', 'Doado', '2024-01-31 15:00:00', 2, '/signatures/donation_auto_15.png', '22.222.222/0001-22', 'Contato B'),
    (1, 'Responsável Aleatório C', '2024-02-20', '11:00:00', 3, 'Instituição C', 'Doado', '2024-02-19 11:30:00', 2, '/signatures/donation_auto_16.png', '33.333.333/0001-33', 'Contato C'),
    (1, 'Responsável Aleatório D', '2024-03-10', '16:45:00', 4, 'Instituição D', 'Doado', '2024-03-09 17:00:00', 2, '/signatures/donation_auto_17.png', '44.444.444/0001-44', 'Contato D'),
    (1, 'Responsável Aleatório E', '2024-03-25', '10:00:00', 1, 'Instituição E', 'Doado', '2024-03-24 10:15:00', 2, '/signatures/donation_auto_18.png', '55.555.555/0001-55', 'Contato E'),
    (1, 'Responsável Aleatório F', '2024-04-05', '13:00:00', 2, 'Instituição F', 'Doado', '2024-04-04 14:00:00', 2, '/signatures/donation_auto_19.png', '66.666.666/0001-66', 'Contato F'),
    (1, 'Responsável Aleatório G', '2024-04-22', '15:30:00', 3, 'Instituição G', 'Doado', '2024-04-21 16:00:00', 2, '/signatures/donation_auto_20.png', '77.777.777/0001-77', 'Contato G'),
    (1, 'Responsável Aleatório H', '2024-05-18', '09:00:00', 4, 'Instituição H', 'Doado', '2024-05-17 09:30:00', 2, '/signatures/donation_auto_21.png', '88.888.888/0001-88', 'Contato H'),
    (1, 'Responsável Aleatório I', '2024-06-02', '11:20:00', 1, 'Instituição I', 'Doado', '2024-06-01 12:00:00', 2, '/signatures/donation_auto_22.png', '99.999.999/0001-99', 'Contato I'),
    (1, 'Responsável Aleatório J', '2024-06-19', '17:00:00', 2, 'Instituição J', 'Doado', '2024-06-18 17:30:00', 2, '/signatures/donation_auto_23.png', '10.101.010/0001-10', 'Contato J'),
    (1, 'Responsável Aleatório K', '2024-07-07', '10:45:00', 3, 'Instituição K', 'Doado', '2024-07-06 11:00:00', 2, '/signatures/donation_auto_24.png', '12.121.212/0001-21', 'Contato K'),
    (1, 'Responsável Aleatório L', '2024-07-21', '14:15:00', 4, 'Instituição L', 'Doado', '2024-07-20 15:00:00', 2, '/signatures/donation_auto_25.png', '13.131.313/0001-31', 'Contato L'),
    (1, 'Responsável Aleatório M', '2024-08-11', '16:00:00', 1, 'Instituição M', 'Doado', '2024-08-10 16:30:00', 2, '/signatures/donation_auto_26.png', '14.141.414/0001-41', 'Contato M'),
    (1, 'Responsável Aleatório N', '2024-08-29', '09:50:00', 2, 'Instituição N', 'Doado', '2024-08-28 10:00:00', 2, '/signatures/donation_auto_27.png', '15.151.515/0001-51', 'Contato N'),
    (1, 'Responsável Aleatório O', '2024-09-14', '12:30:00', 3, 'Instituição O', 'Doado', '2024-09-13 13:00:00', 2, '/signatures/donation_auto_28.png', '16.161.616/0001-61', 'Contato O'),
    (1, 'Responsável Aleatório P', '2024-09-30', '15:00:00', 4, 'Instituição P', 'Doado', '2024-09-29 15:30:00', 2, '/signatures/donation_auto_29.png', '17.171.717/0001-71', 'Contato P'),
    (1, 'Responsável Aleatório Q', '2024-10-16', '10:00:00', 1, 'Instituição Q', 'Doado', '2024-10-15 11:00:00', 2, '/signatures/donation_auto_30.png', '18.181.818/0001-81', 'Contato Q'),
    (1, 'Responsável Aleatório R', '2024-11-01', '14:45:00', 2, 'Instituição R', 'Doado', '2024-10-31 15:00:00', 2, '/signatures/donation_auto_31.png', '19.191.919/0001-91', 'Contato R'),
    (1, 'Responsável Aleatório S', '2024-11-22', '11:10:00', 3, 'Instituição S', 'Doado', '2024-11-21 11:30:00', 2, '/signatures/donation_auto_32.png', '20.202.020/0001-20', 'Contato S'),
    (1, 'Responsável Aleatório T', '2024-12-05', '16:00:00', 4, 'Instituição T', 'Doado', '2024-12-04 16:15:00', 2, '/signatures/donation_auto_33.png', '21.212.121/0001-21', 'Contato T'),
    (1, 'Responsável Aleatório U', '2024-12-20', '09:00:00', 1, 'Instituição U', 'Doado', '2024-12-19 09:30:00', 2, '/signatures/donation_auto_34.png', '23.232.323/0001-32', 'Contato U'),
    (1, 'Responsável Aleatório V', '2023-12-28', '13:30:00', 2, 'Instituição V', 'Doado', '2023-12-27 14:00:00', 2, '/signatures/donation_auto_35.png', '24.242.424/0001-42', 'Contato V'),
    (1, 'Responsável Aleatório X', '2023-12-15', '15:00:00', 3, 'Instituição X', 'Doado', '2023-12-14 15:30:00', 2, '/signatures/donation_auto_36.png', '25.252.525/0001-52', 'Contato X');

-- VINCULAR ITENS 'DOADO' AOS NOVOS TERMOS DE DOAÇÃO
-- Este bloco distribui aleatoriamente todos os itens com status 'Doado' que ainda
-- não estão em nenhum termo, entre os novos termos de doação criados acima.
SET @min_new_term_id = (SELECT MIN(term_id) FROM donation_terms WHERE signature_image_path LIKE '/signatures/donation_auto_%');
SET @max_new_term_id = (SELECT MAX(term_id) FROM donation_terms WHERE signature_image_path LIKE '/signatures/donation_auto_%');
SET @term_id_range = @max_new_term_id - @min_new_term_id + 1;

INSERT INTO `donation_term_items` (`term_id`, `item_id`)
SELECT
    (@min_new_term_id + FLOOR(RAND() * @term_id_range)) AS term_id,
    i.id AS item_id
FROM `items` i
LEFT JOIN `donation_term_items` dti ON i.id = dti.item_id
WHERE i.status = 'Doado' AND dti.term_item_id IS NULL;


-- --- FIM DO SCRIPT ---
SELECT 'Banco de dados e população expandida concluídos com sucesso!' AS status;