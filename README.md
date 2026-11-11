# Reporte Aqui

Plataforma web onde moradores de Videira (SC) registram problemas urbanos — buracos, iluminação, lixo, entulho,
calçadas e sinalização — com foto e localização no mapa, e acompanham o atendimento até a prefeitura resolver.

Projeto acadêmico do curso de Ciência da Computação — UNOESC Videira.

**Tecnologias:** HTML, CSS, JavaScript puro, Bootstrap 5, PHP 8 (sem framework, PDO), MySQL/MariaDB,
Leaflet + OpenStreetMap e Nominatim (geocodificação reversa).

---

## Como rodar no XAMPP

### 1. Copie o projeto

Coloque a pasta `reporte-aqui` dentro de `htdocs`:

- Windows: `C:\xampp\htdocs\reporte-aqui`
- Linux: `/opt/lampp/htdocs/reporte-aqui`
- macOS: `/Applications/XAMPP/htdocs/reporte-aqui`

### 2. Ligue o Apache e o MySQL

No painel do XAMPP, clique em **Start** em *Apache* e em *MySQL*.

### 3. Importe o banco de dados

1. Abra <http://localhost/phpmyadmin>.
2. Clique na aba **Importar**.
3. Escolha o arquivo `database.sql` e clique em **Executar** (ou **Importar**).

O arquivo cria o banco `reporte_aqui`, todas as tabelas e os dados de exemplo.
Importar de novo **apaga tudo e recria** os dados de exemplo — útil para recomeçar os testes.

Pelo terminal, se preferir:

```bash
mysql -u root < database.sql
```

### 4. Confira o `config.php`

O arquivo já vem com o padrão do XAMPP (usuário `root`, sem senha). Só altere se o seu MySQL for diferente:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'reporte_aqui');
define('DB_USER', 'root');
define('DB_PASS', '');
```

`BASE_URL` pode ficar vazio: o endereço é detectado automaticamente.

### 5. Acesse no navegador

<http://localhost/reporte-aqui/>

---

## Usuários de teste

| Perfil | Login | Senha |
|---|---|---|
| Prefeitura (admin) | `admin@reporteaqui.local` | `admin1234` |
| Cidadão | `marcos@email.com` ou CPF `529.982.247-25` | `12345678` |
| Cidadã (2ª conta, para testar apoio e sigilo) | `ana@email.com` ou CPF `390.533.447-05` | `12345678` |

Para criar uma conta nova é preciso um CPF com dígitos verificadores válidos (ex.: `168.995.350-09`).

---

## Requisitos e ajustes do PHP

- PHP 8.0 ou mais novo (testado com PHP 8.3 e MariaDB 10.11).
- Extensões: `pdo_mysql`, `gd`, `fileinfo`, `mbstring` e `curl`. A `exif` é opcional (corrige a rotação de fotos de celular).
- **Se o envio de fotos falhar com a mensagem sobre a extensão GD:** abra o `php.ini` (painel do XAMPP → Apache →
  *Config* → *PHP (php.ini)*), remova o `;` do início da linha `;extension=gd` e reinicie o Apache.
- A pasta `uploads/` precisa de permissão de escrita (no Windows já funciona; no Linux/macOS: `chmod 775 uploads`).
- Limites de envio: o padrão do XAMPP (`upload_max_filesize=40M`, `post_max_size=40M`) já atende às 3 fotos de 5 MB.
- **Internet:** Bootstrap, ícones e Leaflet estão dentro do projeto (`assets/vendor`), mas as imagens do mapa
  (OpenStreetMap) e o preenchimento automático do endereço (Nominatim) precisam de internet. Sem conexão, o mapa fica
  cinza e o endereço pode ser digitado manualmente.

---

## Estrutura de pastas

```
reporte-aqui/
├── config.php            Configuração (banco, mapa, limites das fotos)
├── database.sql          Tabelas + dados de exemplo
├── index.php             Redireciona para /public
├── app/                  Código interno (bloqueado para o navegador)
│   ├── iniciar.php       Carrega tudo e inicia a sessão
│   ├── conexao.php       Conexão PDO
│   ├── funcoes.php       Escape, CSRF, CPF, protocolo, prazos, distância, apoio
│   ├── auth.php          Login, logout, proteção por perfil
│   ├── upload.php        Validação e redimensionamento das fotos
│   ├── admin.php         Filtros do painel
│   └── layout/           Cabeçalho e rodapé
├── public/               Páginas
│   ├── index.php         Início: mapa, filtros, últimas solicitações, números
│   ├── consulta.php      Consulta por protocolo (sem login)
│   ├── como-funciona.php Prazos por tipo e perguntas frequentes
│   ├── cadastro.php · login.php · logout.php
│   ├── nova.php          Nova solicitação em 4 etapas
│   ├── minhas.php        Minhas solicitações (abas)
│   ├── solicitacao.php   Detalhe, linha do tempo, apoio e avaliação
│   └── admin/
│       ├── index.php     Painel da prefeitura
│       └── exportar.php  Relatório CSV
├── api/                  Endpoints JSON
│   ├── ocorrencias.php       Pontos do mapa (público)
│   ├── geocodificar.php      Endereço a partir da coordenada (Nominatim)
│   ├── proximas.php          Solicitação do mesmo tipo a menos de 50 m
│   ├── apoiar.php            Apoiar uma solicitação
│   ├── admin_solicitacao.php Dados para o painel lateral
│   └── admin_atualizar.php   Situação, prioridade, setor, mensagem e nota
├── assets/               CSS, JavaScript, imagens e bibliotecas (vendor)
└── uploads/              Fotos enviadas (nomes aleatórios; scripts bloqueados)
```

## Banco de dados

`usuarios`, `setores`, `categorias`, `subcategorias`, `solicitacoes`, `fotos`, `historico`, `notas_internas`,
`apoios` e `avaliacoes` — todas InnoDB, com chaves estrangeiras e índices.

## Regras de negócio

- Situações: **Recebida → Em análise → Em execução → Concluída** (só avança, nunca volta).
- Mudar a situação exige uma mensagem ao cidadão. Toda mudança (situação, setor, prioridade) grava um registro no
  histórico com data, situação, mensagem e autor. A mudança de prioridade é um registro interno (o cidadão não vê).
- Protocolo no formato `RA-AAAA-0000`, sequencial por ano.
- Previsão = data do registro + prazo do tipo. Passou da previsão e ainda está aberta: **atrasada**.
- Solicitação do mesmo tipo, em aberto, a menos de 50 m: o sistema avisa e oferece o apoio.
- Quem pede sigilo tem o nome exibido apenas para si e para a prefeitura.

| Tipo | Setor | Prazo |
|---|---|---|
| Buraco na via | Secretaria de Obras | 15 dias |
| Iluminação pública | Iluminação Pública | 7 dias |
| Lixo acumulado | Limpeza Urbana | 5 dias |
| Entulho irregular | Fiscalização Ambiental | 10 dias |
| Calçada danificada | Secretaria de Obras | 20 dias |
| Sinalização | Departamento de Trânsito | 10 dias |

## Segurança

- Senhas com `password_hash` / `password_verify`; `session_regenerate_id` no login; cookie de sessão `HttpOnly` e `SameSite=Lax`.
- Token CSRF em todos os formulários e nas chamadas POST da API.
- Todas as consultas com PDO e *prepared statements*; toda saída escapada com `htmlspecialchars`.
- Páginas e APIs protegidas por perfil (cidadão / admin).
- Fotos: tipo real conferido pelo conteúdo, imagem regravada pelo GD (máx. 1600 px), nome aleatório, e a pasta
  `uploads/` não executa scripts.

## Como testar cada fluxo

1. **Área pública (sem login):** na página inicial, use os filtros do mapa e clique num pino. Consulte o protocolo
   `RA-AAAA-0004` (AAAA = ano atual). Abra "Como funciona".
2. **Cadastro:** "Criar conta" com um CPF inválido (mostra erro) e depois com um válido.
3. **Login:** entre como Marcos pelo e-mail ou pelo CPF.
4. **Nova solicitação:** "Nova solicitação" → escolha o tipo → clique no mapa (o endereço é preenchido) → descreva,
   anexe fotos, marque o sigilo → revise e envie. Anote o protocolo.
5. **Aviso de duplicada:** em uma nova solicitação do tipo *Buraco na via*, clique no mapa bem em cima do pino da
   Rua Getúlio Vargas (bairro Amarante, ao norte do centro): aparece o aviso com "Apoiar a solicitação existente".
6. **Apoio:** logado como Marcos, abra uma solicitação da Ana (ex.: final `0008`) e clique em "Apoiar".
7. **Avaliação:** como Marcos, abra a solicitação concluída de final `0003` e dê uma nota.
8. **Atrasada e sigilo:** a de final `0006` está atrasada e foi registrada em sigilo pela Ana — como Marcos ou
   visitante o nome não aparece; como admin, aparece.
9. **Painel da prefeitura:** entre como admin, filtre, clique numa linha, tente mudar a situação sem mensagem
   (erro), depois preencha a mensagem, mude prioridade/setor, escreva uma nota interna e salve. Clique em "Exportar CSV".
10. **Proteção:** logado como cidadão, tente abrir `/public/admin/index.php` — o acesso é negado.
