# GRC Intelligence System (Nome Provisório)

## 1. Descrição e Objetivo
O objetivo deste projeto é desenvolver uma ferramenta local de Governança, Risco e Conformidade (GRC) assistida por Inteligência Artificial (IA). O sistema visa centralizar a gestão de ativos, softwares, clientes e vulnerabilidades da DBSeller, permitindo que um Analista de Segurança utilize linguagem natural para consultar o estado atual da infraestrutura ("Onde estamos") e receber recomendações estratégicas ("Para onde vamos").

Diferente de ferramentas de GRC tradicionais e estáticas, este software utiliza o **Gemini 2.5 Flash Lite** para interpretar comandos, realizar cadastros automáticos e analisar o contexto de segurança (ex: sugerir treinamentos de phishing com base em tendências de ativos).

## 2. Stack Tecnológica
A escolha das tecnologias foca em performance, ambiente local Linux e facilidade de manutenção para um desenvolvedor com background em PHP e Python.

- **Linguagem Backend:** Python (Flask).
- **Frontend:** Vue.js (utilizando Fetch API).
- **Banco de Dados:** SQLite (Armazenamento local, sem dependência de servidores externos).
- **IA:** Google Gemini 2.5 Flash Lite (integração via API).
- **Ambiente:** Otimizado para Linux.

## 3. Modelagem de Dados (Banco de Dados Local)
O modelo foi desenhado para suportar a variação de versões de software e especificidades por cliente.

### Tabela: `clientes`
Armazena as entidades atendidas pela DBSeller.
- `id`: INTEGER PRIMARY KEY (Auto-increment)
- `nome`: TEXT (Ex: "Niterói", "Volta Redonda")

### Tabela: `softwares`
Armazena os projetos base e suas versões/tecnologias específicas.
- `id`: INTEGER PRIMARY KEY (Auto-increment)
- `nome`: TEXT (Ex: "e-Cidade 7.4", "e-Cidade 5.6")
- `git_url`: TEXT (URL base do repositório para aquela versão/projeto)

### Tabela: `instancias_cliente` (Tabela de Ligação)
Conecta clientes aos softwares, definindo a branch específica de cada um.
- `id`: INTEGER PRIMARY KEY
- `cliente_id`: INTEGER (FK -> clientes.id)
- `software_id`: INTEGER (FK -> softwares.id)
- `git_custom_url`: TEXT (Caso o cliente tenha um fork específico, opcional)
- `branch`: TEXT (Ex: "master", "homolog", "v1.2-niteroi")

## 4. Funcionalidades de IA (Gemini Integration)

### A. Cadastro Inteligente
A IA deve ser capaz de interpretar frases como:
> *"Adicione para o cliente Volta Redonda o e-Cidade 7.4 na branch produção"*

**Ação:** O sistema busca o ID de Volta Redonda e do e-Cidade 7.4 e cria o registro na tabela `instancias_cliente`.

### B. Consulta em Linguagem Natural
O usuário poderá perguntar:
> *"Quais clientes usam o Portal Transparência na branch Master?"*

**Ação:** A IA traduz a pergunta em um SELECT SQL complexo unindo as três tabelas e retorna a lista formatada.

### C. Análise de Risco e Próximos Passos
Com base nos dados, a IA poderá sugerir ações:
> *"Com base nos softwares PHP 5.6 ativos, o que devo fazer?"*

**Recomendação da IA:** *"Clientes X e Y utilizam versão obsoleta. Priorizar atualização de ambiente ou aplicar patch de segurança específico."*

## 5. Próximos Passos (Workflow)
- **Backend:** Criar a estrutura Flask e as migrações SQLite.
- **Integração IA:** Configurar o cliente da API do Gemini para conversão de texto em SQL (Text-to-SQL).
- **Frontend:** Desenvolver a interface Vue.js para o chat e visualização das tabelas.
- **Expansão:** Adicionar a tabela de vulnerabilidades para cruzamento com os softwares.

---
> **Nota de Desenvolvimento:** Este documenta apenas o início do projeto, sendo que a arquitetura e as funcionalidades irão escalonar e evoluir conforme as necessidades de segurança.