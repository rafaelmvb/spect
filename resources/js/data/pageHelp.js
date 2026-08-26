/**
 * Conteúdo de ajuda para cada página do painel admin.
 * Cada entrada tem: title, icon (emoji), description, sections[].
 * Matching feito por prefixo de URL (mais específico primeiro).
 */

const HELP = {
    // ─── Dashboard ──────────────────────────────────────────────────────────
    '/dashboard': {
        icon: '📊',
        title: 'Dashboard',
        description: 'Visão geral das métricas de vendas da plataforma em tempo real. Tudo que você precisa para acompanhar o desempenho do negócio em um único lugar.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Receita total, quantidade de vendas e ticket médio do período selecionado',
                    'Taxa de conversão do checkout e abandono de carrinho',
                    'Reembolsos processados e vendas pendentes',
                    'Gráfico de área com evolução de vendas dia a dia',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Selecione o período no topo (hoje / 7 dias / mês / ano / total) — a página atualiza automaticamente',
                    'Clique no ícone de olho para ocultar valores monetários (útil ao compartilhar a tela)',
                    'Passe o mouse sobre o gráfico para ver os valores exatos de cada dia',
                ],
            },
            {
                heading: 'Dica',
                items: [
                    'Compare períodos iguais (ex.: este mês vs. mês anterior) usando o seletor manual para avaliar crescimento real',
                ],
            },
        ],
    },

    // ─── Produtos ──────────────────────────────────────────────────────────
    '/produtos/alunos': {
        icon: '🎓',
        title: 'Alunos',
        description: 'Gerenciamento completo da base de alunos matriculados: progresso, histórico de acesso, dados e ações de suporte.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Lista de todos os alunos com produto(s) vinculado(s), data de matrícula e status de engajamento',
                    'Detalhe por aluno: progresso por módulo, aulas concluídas, último acesso e histórico de compras',
                    'Opções de ação: revogar acesso, reenviar e-mail de acesso, mover entre produtos',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Busque por nome ou e-mail na barra de pesquisa; filtre por produto ou status de engajamento',
                    'Clique em um aluno para abrir o painel lateral com todos os detalhes e ações',
                    'Use "Importar alunos" para fazer matrícula em lote via arquivo CSV (modelo disponível para download)',
                ],
            },
            {
                heading: 'Dica',
                items: [
                    'Alunos com 0% de progresso e matrícula antiga são candidatos a campanhas de reengajamento via e-mail',
                ],
            },
        ],
    },

    '/produtos/cupons': {
        icon: '🏷️',
        title: 'Cupons de Desconto',
        description: 'Criação e gestão de cupons de desconto aplicáveis nos checkouts dos produtos.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Lista de cupons com código, desconto (fixo ou %), usos restantes e validade',
                    'Status de cada cupom: ativo, expirado ou esgotado',
                    'Contagem de utilizações por cupom',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Crie um cupom definindo código único, tipo de desconto (% ou valor fixo) e limite de usos',
                    'Defina validade opcional para cupons de promoções temporárias',
                    'Associe o cupom a um produto específico ou deixe disponível para todos',
                ],
            },
        ],
    },

    '/produtos': {
        icon: '📦',
        title: 'Produtos',
        description: 'Central de gestão de todos os seus produtos digitais: trilhas, ebooks, mentorias, assinaturas e mais.',
        sections: [
            {
                heading: 'Tipos de produto disponíveis',
                items: [
                    'Área de Membros — trilha completa com módulos, aulas, certificados e comunidade',
                    'Link — entrega de arquivo, ebook ou URL após a compra',
                    'Assinatura — cobrança recorrente (mensal, anual, etc.)',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em "+ Novo produto" para criar; escolha o tipo antes de prosseguir',
                    'Use o menu (3 pontos) em cada card para editar, duplicar ou excluir o produto',
                    'Clique no nome do produto para acessar configurações completas: preço, checkout, área de membros',
                ],
            },
            {
                heading: 'Fluxo básico de criação',
                items: [
                    '1. Crie o produto e defina nome, preço e gateway de pagamento',
                    '2. Configure o checkout (página de vendas, métodos de pagamento, order bumps)',
                    '3. Para área de membros: construa módulos e aulas no Member Builder',
                    '4. Teste o fluxo de compra antes de lançar',
                ],
            },
        ],
    },

    // ─── Vendas ──────────────────────────────────────────────────────────
    '/vendas/assinaturas': {
        icon: '🔄',
        title: 'Assinaturas',
        description: 'Visão consolidada de todas as assinaturas recorrentes com MRR e status individual de cada cobrança.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'MRR (Receita Recorrente Mensal) consolidado no topo',
                    'Lista de assinantes com produto, plano, próxima renovação e status',
                    'Status possíveis: Ativa, Em atraso (pagamento falhou), Cancelada',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Monitore assinaturas "em atraso" — o gateway tenta cobrar automaticamente por alguns dias',
                    'Assinaturas canceladas perdem acesso ao produto na data de expiração',
                    'Clique em uma assinatura para ver o histórico completo de cobranças',
                ],
            },
        ],
    },

    '/vendas': {
        icon: '💰',
        title: 'Vendas',
        description: 'Listagem completa de todos os pedidos com filtros avançados, detalhes de cada venda e ações de suporte ao cliente.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Todos os pedidos com status (aprovado, pendente, reembolsado, cancelado), valor e método de pagamento',
                    'Dados do comprador, produto, oferta e UTMs de rastreamento',
                    'Estatísticas rápidas no topo: total aprovado, pendente e reembolsado',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Filtre por período, produto, método de pagamento, status ou busque por nome/e-mail/CPF',
                    'Clique em uma venda para abrir o painel lateral com detalhes completos e ações disponíveis',
                    'Ações por pedido: aprovar manualmente (pendentes), reembolsar, reenviar e-mail de acesso',
                ],
            },
            {
                heading: 'Exportação',
                items: [
                    'Clique em "Exportar" para baixar as vendas filtradas em CSV ou Excel',
                    'Para exportações mais completas, use a aba Relatórios → Exportações',
                ],
            },
        ],
    },

    // ─── Relatórios ──────────────────────────────────────────────────────
    '/relatorios/vendas': {
        icon: '📈',
        title: 'Relatório de Vendas',
        description: 'Análise profunda de receita com gráficos temporais, breakdown por produto, método de pagamento e identificação dos melhores momentos para vender.',
        sections: [
            {
                heading: 'Métricas disponíveis',
                items: [
                    'Receita total, quantidade de vendas, ticket médio e reembolsos no período',
                    'Gráfico de receita por dia (tendência temporal)',
                    'Vendas por hora do dia e por dia da semana (identifique os melhores momentos)',
                    'Top 10 produtos por receita, breakdown por gateway e cupons mais usados',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Selecione o período no seletor superior (7d / 30d / mês / ano / total)',
                    'Use o gráfico de horas para programar disparos de campanha nos picos de conversão',
                    'Compare o top de produtos para decisões de portfólio e promoções',
                ],
            },
        ],
    },

    '/relatorios/alunos': {
        icon: '🎓',
        title: 'Relatório de Alunos',
        description: 'Métricas de engajamento da base de alunos: quem está ativo, quem nunca acessou e os conteúdos mais consumidos.',
        sections: [
            {
                heading: 'Métricas disponíveis',
                items: [
                    'Total de alunos, taxa de engajamento (últimos 30 dias) e nunca acessaram',
                    'Gráfico de novos alunos por mês',
                    'Tabela de alunos ativos e aulas concluídas por produto',
                    'Ranking: top 10 alunos mais engajados e aulas mais assistidas',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Monitore "nunca acessaram" — são alunos que compraram mas não entraram: ótimo alvo de onboarding',
                    'Use o ranking de aulas populares para priorizar a criação de conteúdo similar',
                    'Alta taxa de engajamento indica produto de qualidade; baixa exige intervenção',
                ],
            },
        ],
    },

    '/relatorios/conversao': {
        icon: '🎯',
        title: 'Relatório de Conversão',
        description: 'Funil completo do checkout mostrando onde os visitantes abandonam e a taxa de conversão por produto e fonte de tráfego.',
        sections: [
            {
                heading: 'Métricas disponíveis',
                items: [
                    'Funil: visitas → formulário iniciado → formulário preenchido → convertidos',
                    'Taxa de conversão por produto (quais produtos convertem melhor)',
                    'Conversões por UTM source (qualidade de cada fonte de tráfego)',
                    'Conversões por hora do dia',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Identifique o maior drop-off do funil para priorizar otimizações',
                    'Compare taxa de conversão entre produtos: produtos com baixa conversão podem precisar de nova copy ou preço',
                    'Use UTM source para decidir onde investir mais em tráfego pago',
                ],
            },
        ],
    },

    '/relatorios/produtos': {
        icon: '📦',
        title: 'Performance de Produtos',
        description: 'Tabela comparativa de todos os produtos com métricas de receita, engajamento e reembolso lado a lado.',
        sections: [
            {
                heading: 'Métricas por produto',
                items: [
                    'Receita total e % da receita total (barra proporcional)',
                    'Quantidade de vendas e ticket médio',
                    'Total de alunos e taxa de engajamento (% que acessa)',
                    'Taxa de reembolso (alertas visuais: verde/amarelo/vermelho)',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique nos cabeçalhos de coluna para ordenar por qualquer métrica',
                    'Produtos com taxa de reembolso alta (> 10%) precisam de atenção: revise a oferta ou o suporte',
                    'Produtos com baixo engajamento apesar de muitos alunos indicam problema de entrega de valor',
                ],
            },
        ],
    },

    '/relatorios/engajamento': {
        icon: '⚡',
        title: 'Relatório de Engajamento',
        description: 'Atividade diária na plataforma: usuários ativos, aulas concluídas, checkpoints respondidos e etapas de jornada completadas.',
        sections: [
            {
                heading: 'Métricas disponíveis',
                items: [
                    'DAU médio (Usuários Ativos Diários dos últimos 30 dias)',
                    'Total de aulas concluídas, checkpoints respondidos e etapas de jornada',
                    'Gráfico de usuários ativos por dia, aulas por semana e checkpoints por mês',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Selecione a janela temporal (1m / 3m / 6m / 12m) para diferentes perspectivas',
                    'Quedas no DAU após lançamento indicam falta de conteúdo novo ou suporte',
                    'Compare checkpoints respondidos com aulas concluídas para avaliar profundidade de engajamento',
                ],
            },
        ],
    },

    '/relatorios/retencao': {
        icon: '🔁',
        title: 'Análise de Retenção',
        description: 'Tabela de coorte mostrando que % dos alunos de cada turma permaneceu ativo mês a mês.',
        sections: [
            {
                heading: 'Como ler a tabela',
                items: [
                    'Cada linha é uma coorte (mês de entrada); cada coluna mostra M1, M2, M3... de retenção',
                    'Verde = boa retenção (> 80%), amarelo = atenção, vermelho = churn alto',
                    'M0 = 100% (todos os alunos no mês de entrada)',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Coortes com baixo M1 indicam falha no onboarding (primeiras semanas são críticas)',
                    'Coortes com queda brusca em M3 sugerem falta de conteúdo ou suporte após o começo',
                    'Compare coortes diferentes para medir impacto de mudanças no produto',
                ],
            },
        ],
    },

    '/relatorios/exportacoes': {
        icon: '📥',
        title: 'Exportações',
        description: 'Central de exportação de dados em CSV para análises externas e criação de audiências no Meta Ads.',
        sections: [
            {
                heading: 'Exportações disponíveis',
                items: [
                    'Vendas: CSV com todos os pedidos filtrado por período',
                    'Alunos: CSV completo com nome, e-mail, produto e data de matrícula',
                    'Meta Ads — Audiência de compradores: lista de e-mails de quem comprou por produto',
                    'Meta Ads — Audiência de abandonos: quem iniciou checkout mas não comprou',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Selecione o período e clique no botão de download — o arquivo é gerado instantaneamente',
                    'Suba audiências do Meta Ads no Gerenciador de Anúncios para retargeting e lookalike',
                    'Use o CSV de alunos para importação em ferramentas de CRM ou e-mail marketing externas',
                ],
            },
        ],
    },

    '/relatorios/trilhas': {
        icon: '🗺️',
        title: 'Relatórios de Trilhas',
        description: 'Análise de progresso dos alunos nas jornadas de aprendizado criadas na plataforma.',
        sections: [
            {
                heading: 'Como usar',
                items: [
                    'Selecione uma jornada para ver o progresso consolidado por etapa',
                    'Identifique em qual etapa os alunos mais abandonam a trilha',
                    'Use os dados para ajustar o conteúdo ou o desbloqueio das etapas',
                ],
            },
        ],
    },

    '/relatorios/evolucao-emocional': {
        icon: '🧠',
        title: 'Evolução Emocional',
        description: 'Relatório de evolução dos alunos nas áreas do Mapa Neurofuncional ao longo do tempo.',
        sections: [
            {
                heading: 'Como usar',
                items: [
                    'Filtre por aluno e período para acompanhar a evolução individual',
                    'O gráfico radar mostra o estado atual em cada área emocional',
                    'O gráfico de linha mostra a evolução temporal de cada indicador',
                ],
            },
        ],
    },

    '/relatorios/conteudos': {
        icon: '📚',
        title: 'Relatório de Conteúdos',
        description: 'Métricas de consumo de conteúdo: aulas mais assistidas, tempo médio e taxa de conclusão por módulo.',
        sections: [
            {
                heading: 'Como usar',
                items: [
                    'Identifique aulas com baixa taxa de conclusão — podem ser muito longas ou de difícil compreensão',
                    'Aulas com alto engajamento são referência de formato e conteúdo para novos materiais',
                    'Use os dados para priorizar a atualização de conteúdos desatualizados',
                ],
            },
        ],
    },

    '/relatorios/profissionais-report': {
        icon: '👨‍⚕️',
        title: 'Relatório de Profissionais',
        description: 'Performance dos profissionais cadastrados: agendamentos, avaliações e receita gerada.',
        sections: [
            {
                heading: 'Como usar',
                items: [
                    'Veja quantos agendamentos cada profissional realizou no período',
                    'Compare avaliações médias entre profissionais',
                    'Identifique profissionais com baixa performance para treinamento ou desligamento',
                ],
            },
        ],
    },

    '/relatorios': {
        icon: '📋',
        title: 'Central de Relatórios',
        description: 'Ponto de acesso a todos os relatórios analíticos da plataforma.',
        sections: [
            {
                heading: 'Relatórios disponíveis',
                items: [
                    'Vendas — receita, gráficos temporais e breakdown por produto',
                    'Alunos — engajamento, retenção e conteúdos mais consumidos',
                    'Conversão — funil de checkout e taxa por produto/fonte de tráfego',
                    'Exportações — CSV de vendas e alunos; audiências para Meta Ads',
                    'Trilhas, Engajamento, Retenção, Evolução Emocional e mais',
                ],
            },
        ],
    },

    // ─── Configurações ──────────────────────────────────────────────────
    '/configuracoes': {
        icon: '⚙️',
        title: 'Configurações',
        description: 'Central de configurações técnicas da plataforma: e-mail, armazenamento, moedas, domínio e integrações.',
        sections: [
            {
                heading: 'Abas disponíveis',
                items: [
                    'E-mail — Configure o provedor de e-mail transacional (SMTP, SendGrid, Amazon SES). Obrigatório para enviar acessos aos alunos',
                    'Storage — Escolha onde armazenar arquivos e mídias (local, S3, Cloudflare R2, Wasabi)',
                    'Moedas — Gerencie moedas aceitas e taxas de câmbio (manual ou via API Frankfurter)',
                    'Traduções — Customize os textos do checkout em PT-BR, EN e ES',
                    'Cron — Configure o agendador de tarefas para e-mails e cobranças recorrentes',
                ],
            },
            {
                heading: 'Configuração inicial recomendada',
                items: [
                    '1. Configure o e-mail transacional — sem isso, os alunos não recebem o link de acesso',
                    '2. Configure o storage externo (R2 ou S3) para não depender do disco local',
                    '3. Configure o Cron (essencial para assinaturas e e-mail marketing)',
                ],
            },
        ],
    },

    // ─── Financeiro ──────────────────────────────────────────────────────
    '/financeiro/comissoes': {
        icon: '💸',
        title: 'Comissões e Repasses',
        description: 'Gestão do fluxo de aprovação e pagamento de comissões para profissionais e co-produtores.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'KPIs: valor total bruto, pendente de aprovação, aprovado e já pago',
                    'Sumário de valor a repassar por profissional',
                    'Lista paginada de comissões filtrável por status',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Filtre por "Pendente" para focar no que precisa de aprovação',
                    'Clique em "Aprovar" para validar a comissão antes do pagamento',
                    'Após o repasse, marque como "Pago" com uma observação (ex.: chave Pix ou comprovante)',
                ],
            },
        ],
    },

    '/financeiro': {
        icon: '💳',
        title: 'Dashboard Financeiro',
        description: 'Visão consolidada da saúde financeira da plataforma: MRR, ARR, receita mensal e análise por produto e método de pagamento.',
        sections: [
            {
                heading: 'Métricas disponíveis',
                items: [
                    'MRR (Monthly Recurring Revenue) e ARR (Annual Recurring Revenue)',
                    'Receita do mês atual vs. mês anterior (variação percentual)',
                    'Gráfico de receita mensal, receita por produto (donut) e por método de pagamento',
                    'Tabela de top produtos por número de pedidos e receita',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Use o filtro de janela temporal (3/6/12/24 meses) para diferentes perspectivas',
                    'Receita por método de pagamento ajuda a negociar taxas com os gateways',
                    'Acompanhe o MRR mensalmente para medir crescimento real do negócio',
                ],
            },
        ],
    },

    // ─── Email Marketing ─────────────────────────────────────────────────
    '/email-marketing': {
        icon: '📧',
        title: 'Email Marketing',
        description: 'Criação e disparo de campanhas de e-mail para alunos e compradores da plataforma.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Lista de campanhas com status (rascunho / enviando / enviado / cancelado)',
                    'Contador de e-mails enviados vs. total de destinatários',
                    'Aba "Configuração" com status do sistema de envio (cron e queue)',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Certifique-se de ter o e-mail transacional configurado em Configurações antes de disparar',
                    'Crie uma campanha definindo assunto, destinatários (todos alunos ou por produto) e conteúdo',
                    'O envio ocorre em lotes de 30/min — campanhas grandes levam alguns minutos para completar',
                ],
            },
            {
                heading: 'Requisitos técnicos',
                items: [
                    'O Cron (agendador) precisa estar configurado para que os e-mails sejam processados',
                    'Verifique a aba "Configuração" se os envios não estiverem funcionando',
                ],
            },
        ],
    },

    // ─── Profissionais ───────────────────────────────────────────────────
    '/profissionais': {
        icon: '👨‍⚕️',
        title: 'Profissionais',
        description: 'Cadastro, aprovação e gestão de profissionais disponíveis para agendamentos na plataforma.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Lista de profissionais com especialidade, status (ativo/inativo) e valor por hora',
                    'Status de aprovação: pendente, aprovado ou rejeitado',
                    'KPIs: total cadastrado, ativos e inativos',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Cadastre profissionais manualmente ou aprove quem se cadastrou via formulário público',
                    'Rejeite candidatos com motivo que será exibido ao profissional',
                    'Use "Criar acesso" para gerar usuário e senha temporária do painel do profissional',
                ],
            },
        ],
    },

    // ─── Reembolsos ──────────────────────────────────────────────────────
    '/reembolsos': {
        icon: '↩️',
        title: 'Reembolsos',
        description: 'Fila de solicitações de reembolso enviadas por alunos, com fluxo de aprovação e rejeição.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Solicitações de reembolso com data, aluno, produto, valor e motivo',
                    'Status: pendente, aprovado ou rejeitado',
                    'Observação interna do admin (visível apenas para você)',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Filtre por status "Pendente" para ver o que precisa de ação',
                    'Clique "Aprovar" para processar o reembolso (automático no gateway quando disponível)',
                    'Clique "Rejeitar" adicionando um motivo que será comunicado ao aluno',
                ],
            },
            {
                heading: 'Dica',
                items: [
                    'Configure a política de reembolso de cada produto em Produto → Reembolso para padronizar o prazo e o fluxo (manual ou automático)',
                ],
            },
        ],
    },

    // ─── Jornadas ────────────────────────────────────────────────────────
    '/jornadas': {
        icon: '🗺️',
        title: 'Jornadas de Aprendizado',
        description: 'Motor de trilhas sequenciais que guia o aluno por etapas com desbloqueio automático ou manual.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Grade de jornadas com capa, status, contagem de etapas e produtos vinculados',
                    'Painel lateral para gerenciar etapas de cada jornada',
                    'Tipos de desbloqueio: livre (todas abertas), sequencial (uma por vez) ou manual (você desbloqueia)',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Crie uma jornada e defina o modo de desbloqueio geral',
                    'Adicione etapas e vincule conteúdos (produto, seção ou módulo específico) a cada etapa',
                    'O aluno vê o progresso na trilha e é notificado quando a próxima etapa é desbloqueada',
                ],
            },
            {
                heading: 'Integrações',
                items: [
                    'Vincule checkpoints a etapas para coletar feedback antes de desbloquear a próxima',
                    'O progresso das jornadas aparece no Relatório → Trilhas',
                ],
            },
        ],
    },

    // ─── Plugins ─────────────────────────────────────────────────────────
    '/gerenciar-plugins': {
        icon: '🧩',
        title: 'Plugins',
        description: 'Central de extensões para adicionar funcionalidades personalizadas à plataforma.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Aba "Instalados": plugins ativos com versão, categoria e ações',
                    'Aba "Loja": explore e instale plugins oficiais da loja',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Instale plugins da loja clicando em "Instalar" — o download e instalação são automáticos',
                    'Para plugins externos, clique em "Instalar ZIP" e faça upload do arquivo',
                    'Ative ou desative plugins sem perder configurações; exclua apenas quando não precisar mais',
                ],
            },
            {
                heading: 'Atenção',
                items: [
                    'Instale somente plugins de fontes confiáveis — plugins têm acesso total ao código da plataforma',
                    'Após instalar um plugin, verifique se novas abas ou menus apareceram no painel',
                ],
            },
        ],
    },

    // ─── Banners ─────────────────────────────────────────────────────────
    '/banners': {
        icon: '🖼️',
        title: 'Banners',
        description: 'Gestão de banners informativos ou promocionais exibidos na área de membros e/ou painel admin.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Cards com preview, título, subtítulo, link e badge de destino (área de membros / painel / ambos)',
                    'Status de cada banner: ativo ou inativo',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em "+ Novo banner" para criar; faça upload da imagem, defina título e link de destino',
                    'Selecione onde o banner aparece: área de membros dos alunos, painel admin ou ambos',
                    'Ative ou desative banners com o toggle sem precisar excluir',
                ],
            },
        ],
    },

    // ─── Mapa Neuro ──────────────────────────────────────────────────────
    '/mapa-neuro': {
        icon: '🧠',
        title: 'Mapa Neurofuncional',
        description: 'Ferramenta de avaliação emocional para acompanhar a evolução dos alunos em diferentes dimensões de bem-estar.',
        sections: [
            {
                heading: 'Aba Configuração',
                items: [
                    'Crie áreas emocionais (ex.: Autoconfiança, Foco, Clareza) com cor e ícone identificadores',
                    'Adicione indicadores a cada área com escala customizável (ex.: 1 a 10)',
                ],
            },
            {
                heading: 'Aba Análises',
                items: [
                    'Gráfico radar: estado atual do aluno em todas as áreas (média dos scores)',
                    'Gráfico de linha: evolução temporal de cada indicador',
                    'Filtre por aluno e período para acompanhamento individual',
                ],
            },
            {
                heading: 'Como alimentar os dados',
                items: [
                    'Lance scores manualmente no modal de lançamento por aluno/indicador/data',
                    'Vincule checkpoints ao mapa para que o aluno preencha automaticamente',
                ],
            },
        ],
    },

    // ─── Checkpoints ─────────────────────────────────────────────────────
    '/checkpoints': {
        icon: '✅',
        title: 'Checkpoints',
        description: 'Formulários de avaliação vinculáveis a etapas de jornadas para coletar feedback, destravar próximas etapas e alimentar o Mapa Neuro.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Lista de checkpoints com nome, vinculação à etapa de jornada e contagem de respostas',
                    'Painel lateral de criação com 6 tipos de questão: texto, área de texto, radio, checkbox, escala e data',
                    'Aba de respostas com distribuição percentual por questão',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Crie um checkpoint e adicione as perguntas arrastando para reordenar',
                    'Vincule a uma etapa de jornada — o aluno precisa responder para desbloquear a próxima etapa',
                    'Ative "Desbloquear próxima etapa ao responder" para uso automático',
                ],
            },
        ],
    },

    // ─── Usuários ────────────────────────────────────────────────────────
    '/usuarios/equipe': {
        icon: '👥',
        title: 'Equipe',
        description: 'Gerenciamento da equipe com cargos, permissões granulares por módulo e log de auditoria.',
        sections: [
            {
                heading: 'Aba Cargos',
                items: [
                    'Crie cargos (ex.: Suporte, Editor de Conteúdo) com permissões específicas',
                    'Configure 11 permissões por cargo: ver vendas, gerenciar alunos, editar produtos, etc.',
                    'Restrinja um cargo a determinados produtos para acesso limitado',
                ],
            },
            {
                heading: 'Aba Membros',
                items: [
                    'Convide membros por e-mail — eles recebem um link para criar senha',
                    'Atribua um cargo a cada membro para definir o que podem fazer',
                    'Remova membros imediatamente revogando o acesso',
                ],
            },
            {
                heading: 'Aba Logs (admin)',
                items: [
                    'Rastreie todas as ações da equipe: quem fez o quê, quando e de qual IP',
                    'Use os logs para auditoria de segurança ou investigação de incidentes',
                ],
            },
        ],
    },

    '/usuarios': {
        icon: '👤',
        title: 'Usuários',
        description: 'Gestão de contas de infoprodutores na plataforma (visível apenas para o admin master).',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Lista de infoprodutores com avatar, badge "Master", e-mail e data de cadastro',
                    'Ações de criar, editar e excluir contas',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em "+ Novo usuário" para criar um infoprodutor com nome, e-mail e senha',
                    'A conta master não pode ser excluída (proteção de segurança)',
                    'Cada infoprodutor tem um espaço isolado (tenant) com seus próprios produtos e alunos',
                ],
            },
        ],
    },

    // ─── Comunidade Admin ────────────────────────────────────────────────
    '/comunidade-admin': {
        icon: '💬',
        title: 'Comunidade',
        description: 'Moderação e gestão de toda a atividade da comunidade: posts, stories, eventos, grupos e denúncias.',
        sections: [
            {
                heading: 'Abas disponíveis',
                items: [
                    'Posts — crie, edite, oculte ou exclua posts visíveis para os alunos por produto',
                    'Stories — crie stories com imagem/vídeo, cor de fundo, duração e visibilidade',
                    'Eventos — crie eventos online ou presenciais com data, local e link de inscrição',
                    'Grupos — crie grupos públicos ou privados e gerencie membros',
                    'Denúncias — fila de moderação com ações de resolver ou descartar',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Filtre o conteúdo por produto para gerenciar comunidades separadas',
                    'Resolva denúncias ocultando o conteúdo ou descartando o report',
                    'Stories expiram automaticamente após o tempo configurado',
                ],
            },
        ],
    },

    // ─── Agendamentos ────────────────────────────────────────────────────
    '/agendamentos': {
        icon: '📅',
        title: 'Agendamentos',
        description: 'Calendário completo de agendamentos com gestão de disponibilidade semanal dos profissionais.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Calendário mensal navegável com agendamentos coloridos por status',
                    'Lista do dia ao clicar em uma data',
                    'Detalhe do agendamento: cliente, profissional, serviço, status e observações',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em um dia para ver ou criar agendamentos nessa data',
                    'Crie manualmente selecionando profissional, data e o sistema carrega os horários disponíveis',
                    'Configure a disponibilidade semanal de cada profissional no painel lateral (dias, horário e duração de slot)',
                ],
            },
            {
                heading: 'Status dos agendamentos',
                items: [
                    'Confirmado (azul) — aguardando atendimento',
                    'Concluído (verde) — sessão realizada',
                    'Cancelado (vermelho) — cancelado pelo cliente ou profissional',
                ],
            },
        ],
    },

    // ─── Mensagens ───────────────────────────────────────────────────────
    '/mensagens': {
        icon: '💬',
        title: 'Mensagens',
        description: 'Campanhas de mensagens via WhatsApp ou SMS para alunos e compradores da plataforma.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Lista de campanhas com status, provedor e contagem de envios',
                    'Ações de editar, disparar e cancelar campanhas',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Configure o provedor de mensagens em Configurações antes de criar campanhas',
                    'Crie a campanha definindo audiência (todos / por produto), mensagem e provedor',
                    'Dispare manualmente após revisar a lista de destinatários',
                ],
            },
        ],
    },

    // ─── Áudios ───────────────────────────────────────────────────────────
    '/musicas': {
        icon: '🎵',
        title: 'Áudios',
        description: 'Biblioteca de áudios ambiente disponibilizados para reprodução pelos alunos na área de membros.',
        sections: [
            {
                heading: 'Como usar',
                items: [
                    'Faça upload de faixas de áudio com título, artista e imagem de capa',
                    'Organize por gênero ou categoria para facilitar a descoberta pelos alunos',
                    'Os áudios ficam disponíveis na seção "Áudios" da área de membros',
                ],
            },
        ],
    },

    // ─── Area de Membros Builder ─────────────────────────────────────────
    '/member-builder': {
        icon: '🏗️',
        title: 'Construtor da Área de Membros',
        description: 'Editor visual para montar a estrutura da trilha: seções, módulos, aulas e configurações de acesso.',
        sections: [
            {
                heading: 'Estrutura do conteúdo',
                items: [
                    'Seção → Módulo → Aula (hierarquia de 3 níveis)',
                    'Seções agrupam módulos temáticos (ex.: "Módulo 1 — Fundamentos")',
                    'Cada aula pode ser: vídeo (YouTube/Vimeo/Panda), texto, PDF, quiz ou arquivo',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Crie seções pelo botão "+ Seção", depois adicione módulos dentro de cada seção',
                    'Clique em um módulo para adicionar/editar aulas no painel lateral',
                    'Use o toggle "Módulo gratuito" para liberar módulos de demonstração sem compra',
                    'Configure liberação por gotejamento (drip): X dias após matrícula ou em data específica',
                ],
            },
            {
                heading: 'Abas do construtor',
                items: [
                    'Módulos — estrutura de conteúdo',
                    'Turmas — grupos de alunos com acesso diferenciado',
                    'Progresso — acompanhamento global',
                    'Certificado — configure o modelo e os critérios de emissão',
                    'Gamificação — pontos e conquistas',
                ],
            },
        ],
    },

    // ─── Assinaturas (relatorios) ────────────────────────────────────────
    '/relatorios/assinaturas': {
        icon: '📊',
        title: 'Relatório de Assinaturas',
        description: 'Análise de assinaturas recorrentes com churn, crescimento e MRR.',
        sections: [
            {
                heading: 'Como usar',
                items: [
                    'Acompanhe o crescimento do MRR mês a mês',
                    'Monitore churn (cancelamentos) para ajustar estratégias de retenção',
                    'Compare novas assinaturas vs. cancelamentos para entender o net growth',
                ],
            },
        ],
    },

    // ─── Meu Perfil ──────────────────────────────────────────────────────
    '/meu-perfil': {
        icon: '🧑‍💼',
        title: 'Meu Perfil',
        description: 'Configurações da sua conta pessoal: nome, avatar, e-mail e senha.',
        sections: [
            {
                heading: 'O que você pode editar',
                items: [
                    'Nome de exibição e foto de perfil (avatar)',
                    'Endereço de e-mail para login e notificações',
                    'Senha de acesso — requer a senha atual para confirmar',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Preencha os campos e clique em Salvar para aplicar as mudanças',
                    'Ao trocar o e-mail, use o novo endereço no próximo login',
                    'Escolha uma foto quadrada para melhor exibição no avatar',
                ],
            },
        ],
    },

    // ─── Conquistas ──────────────────────────────────────────────────────
    '/conquistas': {
        icon: '🏆',
        title: 'Conquistas',
        description: 'Sistema de gamificação: crie medalhas e troféus que os alunos desbloqueiam ao atingir metas nas trilhas.',
        sections: [
            {
                heading: 'O que são conquistas',
                items: [
                    'Badges atribuídos automaticamente ao aluno quando ele cumpre um critério (ex.: concluir 100% da trilha)',
                    'Cada conquista tem nome, ícone e descrição visíveis na área de membros',
                    'Alunos veem suas conquistas no perfil e podem compartilhar nas redes sociais',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em "+ Nova conquista" e defina nome, ícone e critério de desbloqueio',
                    'Vincule a conquista a um produto para que ela seja concedida automaticamente',
                    'Acompanhe quantos alunos já conquistaram cada badge na lista',
                ],
            },
        ],
    },

    // ─── Editar Produto ───────────────────────────────────────────────────
    '/produtos/*/edit': {
        icon: '✏️',
        title: 'Editar Produto',
        description: 'Configurações completas do produto: dados gerais, preço, área de membros, afiliados e muito mais.',
        sections: [
            {
                heading: 'Abas principais',
                items: [
                    'Geral — nome, descrição, categoria, imagem de capa e visibilidade',
                    'Preço — valor, moeda, tipo (único / recorrente / gratuito)',
                    'Área de membros — slug de acesso e configurações do player',
                    'Afiliados — percentual de comissão e aprovação automática',
                    'Pixels — Facebook Pixel, Google Tag Manager e outros rastreamentos',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Navegue pelas abas e salve cada seção separadamente',
                    'O slug da área de membros define a URL que os alunos acessam',
                    'Alterações no preço não afetam compras já realizadas',
                ],
            },
        ],
    },

    // ─── Editar Checkout ──────────────────────────────────────────────────
    '/produtos/*/checkout/edit': {
        icon: '🛒',
        title: 'Editar Checkout',
        description: 'Editor visual da página de checkout: layout, cores, textos, depoimentos e order bumps.',
        sections: [
            {
                heading: 'Elementos editáveis',
                items: [
                    'Cabeçalho — logo, título e subtítulo da oferta',
                    'Cores e fontes — paleta principal alinhada à marca',
                    'Depoimentos — adicione prova social com foto, nome e texto',
                    'Order Bump — oferta complementar exibida antes de finalizar a compra',
                    'Garantia — selo de garantia configurável (dias e ícone)',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Edite os campos no painel esquerdo e veja a prévia em tempo real',
                    'Ative ou desative seções com os toggles para simplificar a página',
                    'Clique em Salvar e acesse o link de checkout para conferir o resultado final',
                ],
            },
        ],
    },

    // ─── Editar Upsell ────────────────────────────────────────────────────
    '/produtos/*/upsell-page/edit': {
        icon: '⬆️',
        title: 'Editar Página de Upsell',
        description: 'Página exibida logo após a compra com uma oferta adicional de maior valor.',
        sections: [
            {
                heading: 'O que é o upsell',
                items: [
                    'Oferta especial exibida ao cliente imediatamente após confirmar a compra principal',
                    'O cliente pode aceitar com um clique sem precisar reinserir os dados de pagamento',
                    'Ideal para oferecer uma versão premium, módulos bônus ou produtos complementares',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Configure o produto upsell (pode ser o mesmo ou outro produto do catálogo)',
                    'Escreva um título e texto persuasivo destacando o valor adicional',
                    'Defina o preço especial exclusivo para o upsell (diferente do preço normal)',
                    'Ative a página para ela ser exibida automaticamente após a compra',
                ],
            },
        ],
    },

    // ─── Editar Downsell ──────────────────────────────────────────────────
    '/produtos/*/downsell-page/edit': {
        icon: '⬇️',
        title: 'Editar Página de Downsell',
        description: 'Página exibida quando o cliente recusa o upsell, com uma oferta alternativa de menor valor.',
        sections: [
            {
                heading: 'O que é o downsell',
                items: [
                    'Segunda chance de converter após a recusa do upsell',
                    'Oferta mais acessível (preço menor, versão simplificada ou parcelamento maior)',
                    'Reduz o custo de oportunidade de clientes que recusaram a oferta principal',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Configure o produto e preço do downsell (geralmente 30–50% mais barato que o upsell)',
                    'Use um argumento diferente do upsell — foque em acessibilidade, não em valor extra',
                    'Ative somente quando o upsell também estiver ativo',
                ],
            },
        ],
    },

    // ─── Comentários Member Builder ───────────────────────────────────────
    '/produtos/*/member-builder/comments': {
        icon: '💬',
        title: 'Comentários das Aulas',
        description: 'Moderação dos comentários que os alunos fazem nas aulas dentro da área de membros.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Todos os comentários de todas as aulas do produto em uma única lista',
                    'Filtro por aula, status (aprovado / pendente / bloqueado) e data',
                    'Contagem de curtidas e respostas por comentário',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Aprove, oculte ou exclua comentários clicando nas ações de cada linha',
                    'Responda diretamente da tela de moderação — a resposta aparece na área do aluno',
                    'Configure em Produto → Área de membros se comentários precisam de aprovação prévia',
                ],
            },
        ],
    },

    // ─── Integrações ──────────────────────────────────────────────────────
    '/integracoes': {
        icon: '🔌',
        title: 'Integrações',
        description: 'Conecte a plataforma a ferramentas externas: e-mail marketing, webhooks, pixels e gateways de pagamento.',
        sections: [
            {
                heading: 'Tipos de integração',
                items: [
                    'E-mail marketing — ActiveCampaign, Mailchimp, RD Station e outros',
                    'Webhooks — envie eventos de compra, cancelamento e acesso para qualquer URL',
                    'Pixels — Facebook, Google Ads, TikTok e Taboola',
                    'Gateways de pagamento — PagSeguro, Stripe, Pix e cartão',
                    'Notificações — WhatsApp e SMS via integração de mensagens',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Selecione a integração desejada e insira as credenciais (API key, secret, webhook URL)',
                    'Teste a conexão com o botão de verificação antes de ativar em produção',
                    'As integrações são ativadas por produto — configure por produto em Editar → Pixels',
                ],
            },
        ],
    },

    // ─── Aplicações API ───────────────────────────────────────────────────
    '/aplicacoes-api': {
        icon: '🔑',
        title: 'Aplicações API',
        description: 'Gerencie chaves de API para integrar sistemas externos com a plataforma via REST API.',
        sections: [
            {
                heading: 'O que são aplicações API',
                items: [
                    'Credenciais (Client ID + Secret) que permitem autenticar chamadas à API da plataforma',
                    'Cada aplicação tem escopos de permissão: leitura de vendas, gestão de alunos, etc.',
                    'Use para integrar ERPs, CRMs, dashboards externos ou automações próprias',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em "+ Nova Aplicação", defina nome e escopos necessários',
                    'Copie o Client Secret logo após criar — ele não é exibido novamente',
                    'Revogue imediatamente qualquer chave comprometida para evitar acesso indevido',
                ],
            },
        ],
    },

    // ─── IA ───────────────────────────────────────────────────────────────
    '/ia': {
        icon: '🤖',
        title: 'Assistente IA',
        description: 'Ferramentas de Inteligência Artificial integradas para criar conteúdo, analisar dados e automatizar tarefas.',
        sections: [
            {
                heading: 'O que você pode fazer',
                items: [
                    'Gerar descrições de produtos, textos de checkout e e-mails de campanha',
                    'Criar roteiros de aulas e resumos de módulos automaticamente',
                    'Analisar métricas e obter insights sobre vendas e engajamento',
                    'Responder perguntas sobre os seus produtos e alunos com contexto da plataforma',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Digite o que deseja criar ou analise no campo de chat',
                    'Forneça contexto sobre o produto ou audiência para resultados mais precisos',
                    'Copie o conteúdo gerado e cole diretamente nos campos do produto ou campanha',
                ],
            },
        ],
    },

    // ─── Posts da Área de Membros ─────────────────────────────────────────
    '/member-posts': {
        icon: '📝',
        title: 'Posts da Área de Membros',
        description: 'Gerenciamento dos posts publicados dentro das áreas de membros de todos os produtos.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Todos os posts de todas as áreas de membros em uma lista centralizada',
                    'Informações: produto, autor, data de publicação e número de curtidas/comentários',
                    'Filtros por produto, status e data para encontrar conteúdo rapidamente',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Crie posts de texto, imagem ou vídeo para engajar alunos na área de membros',
                    'Edite ou exclua posts diretamente desta tela sem precisar acessar cada produto',
                    'Posts fixados aparecem no topo do feed de todos os alunos do produto',
                ],
            },
        ],
    },

    // ─── Relatórios da Área de Membros ────────────────────────────────────
    '/member-reports': {
        icon: '🚩',
        title: 'Denúncias da Área de Membros',
        description: 'Fila de moderação de conteúdo denunciado por alunos dentro das áreas de membros.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Lista de denúncias com: quem denunciou, qual conteúdo e motivo informado',
                    'Tipo do conteúdo denunciado: post, comentário ou resposta',
                    'Status da denúncia: pendente, resolvida ou descartada',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Revise o conteúdo denunciado clicando no link para abrir o original',
                    '"Resolver" — oculta o conteúdo da área de membros',
                    '"Descartar" — mantém o conteúdo e fecha a denúncia sem ação',
                    'Denúncias recorrentes de um mesmo usuário podem indicar abuso — considere banimento',
                ],
            },
        ],
    },

    // ─── Destaques da Home da Área de Membros ────────────────────────────
    '/member-home-featured': {
        icon: '⭐',
        title: 'Destaques da Home',
        description: 'Conteúdo em destaque exibido na página inicial da área de membros para engajar os alunos.',
        sections: [
            {
                heading: 'O que são destaques',
                items: [
                    'Banners ou cards que aparecem no topo da home da área de membros',
                    'Podem apontar para aulas específicas, módulos, links externos ou anúncios',
                    'Controlados por produto — cada produto tem seus próprios destaques',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em "+ Novo Destaque" e selecione o tipo (banner, card de aula, link)',
                    'Configure título, imagem, descrição e destino do clique',
                    'Reordene arrastando para controlar a ordem de exibição',
                    'Desative destaques temporariamente sem precisar excluir',
                ],
            },
        ],
    },

    // ─── Comprovação de Venda ─────────────────────────────────────────────
    '/vendas/*/comprovacao': {
        icon: '🧾',
        title: 'Comprovação de Venda',
        description: 'Detalhes completos de uma venda específica com histórico de pagamentos e dados do comprador.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Dados do comprador: nome, e-mail, CPF/CNPJ e endereço',
                    'Detalhes da transação: produto, valor, parcelas e status do pagamento',
                    'Histórico de tentativas de cobrança e estornos',
                    'Link de comprovante para envio ao cliente',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Use para suporte: confirme o pagamento antes de liberar acesso manualmente',
                    'Copie o link de comprovante para enviar ao cliente via e-mail ou WhatsApp',
                    'Inicie um reembolso ou estorno diretamente desta tela se necessário',
                ],
            },
        ],
    },

    // ─── Exportar Comprovações ────────────────────────────────────────────
    '/vendas/comprovacao/exportar': {
        icon: '📤',
        title: 'Exportar Comprovações',
        description: 'Exportação em lote de comprovantes de pagamento para relatórios fiscais ou suporte ao cliente.',
        sections: [
            {
                heading: 'Como usar',
                items: [
                    'Selecione o período e os filtros desejados (produto, status, gateway)',
                    'Clique em Exportar para gerar o arquivo CSV ou PDF',
                    'O arquivo inclui: data, comprador, produto, valor e status de cada transação',
                    'Use para declarações fiscais, auditorias ou relatórios de comissão',
                ],
            },
        ],
    },

    // ─── Painel Profissional ──────────────────────────────────────────────
    '/p/dashboard': {
        icon: '📊',
        title: 'Dashboard do Profissional',
        description: 'Visão geral da sua agenda, agendamentos do dia e métricas de atendimento.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Próximos agendamentos confirmados com horário e nome do cliente',
                    'Total de atendimentos do mês e receita gerada',
                    'Atalhos rápidos para confirmar, cancelar ou marcar como concluído',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Acesse todos os dias para ver sua agenda do dia e tomar ações rápidas',
                    'Clique em um agendamento para ver os detalhes completos do cliente',
                    'Use os atalhos do painel para responder confirmações sem sair da tela',
                ],
            },
        ],
    },

    '/p/perfil': {
        icon: '🧑‍💼',
        title: 'Perfil Profissional',
        description: 'Suas informações públicas exibidas para clientes na página de agendamento.',
        sections: [
            {
                heading: 'O que você pode editar',
                items: [
                    'Foto de perfil, nome profissional e bio (aparece na página pública)',
                    'Especialidades e áreas de atuação',
                    'Links de redes sociais e site',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Mantenha o perfil atualizado — os clientes veem essas informações ao agendar',
                    'Use uma foto profissional e uma bio clara para aumentar a conversão',
                    'Clique em "Ver página pública" para conferir como os clientes te veem',
                ],
            },
        ],
    },

    '/p/agenda': {
        icon: '📅',
        title: 'Minha Agenda',
        description: 'Calendário pessoal com todos os seus agendamentos confirmados, pendentes e concluídos.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Calendário mensal com agendamentos coloridos por status',
                    'Visão de dia com todos os horários ocupados e livres',
                    'Filtros por status: confirmado, pendente, concluído, cancelado',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em um agendamento para confirmar, cancelar ou marcar como concluído',
                    'Use a visão de dia para gerenciar sua agenda hora a hora',
                    'Bloqueie horários específicos em Disponibilidade para evitar sobreposições',
                ],
            },
        ],
    },

    '/p/disponibilidade': {
        icon: '⏰',
        title: 'Disponibilidade',
        description: 'Configure os dias e horários em que você aceita agendamentos.',
        sections: [
            {
                heading: 'O que você pode configurar',
                items: [
                    'Dias da semana disponíveis com horário de início e fim',
                    'Duração padrão do slot de atendimento (ex.: 1 hora)',
                    'Intervalo entre atendimentos (tempo de descanso)',
                    'Bloqueios específicos de data (férias, feriados)',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Ative os dias que você trabalha e defina o horário de cada dia',
                    'Ajuste a duração do slot conforme o tempo médio de cada atendimento',
                    'Use bloqueios para datas específicas sem precisar desativar o dia inteiro',
                ],
            },
        ],
    },

    '/p/servicos': {
        icon: '🛠️',
        title: 'Meus Serviços',
        description: 'Catálogo de serviços que você oferece com preço, duração e descrição.',
        sections: [
            {
                heading: 'O que são serviços',
                items: [
                    'Cada serviço é uma modalidade de atendimento que o cliente pode escolher ao agendar',
                    'Exemplos: "Consulta 1h", "Sessão de mentoria", "Avaliação inicial"',
                    'Cada serviço tem preço, duração e descrição próprios',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Clique em "+ Novo serviço" e defina nome, duração, preço e descrição',
                    'Ative ou desative serviços sem precisar excluir',
                    'A duração do serviço sobrepõe o slot padrão de disponibilidade',
                ],
            },
        ],
    },

    '/p/portfolio': {
        icon: '🖼️',
        title: 'Portfólio',
        description: 'Galeria de trabalhos e cases de sucesso exibidos na sua página pública de agendamento.',
        sections: [
            {
                heading: 'O que você pode adicionar',
                items: [
                    'Imagens de projetos, resultados ou materiais de trabalho',
                    'Título e descrição de cada item para contextualizar o cliente',
                    'Links externos para projetos, sites ou publicações',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Faça upload das imagens e adicione título e descrição',
                    'Reordene os itens arrastando para destacar os melhores trabalhos',
                    'Use o portfólio para aumentar a credibilidade e converter mais agendamentos',
                ],
            },
        ],
    },

    '/p/avaliacoes': {
        icon: '⭐',
        title: 'Avaliações',
        description: 'Depoimentos e avaliações deixados pelos clientes após os atendimentos.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Todas as avaliações recebidas com nota, comentário e data',
                    'Média geral de avaliações exibida no seu perfil público',
                    'Opção de responder publicamente a cada avaliação',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Responda avaliações para mostrar comprometimento e profissionalismo',
                    'Avaliações negativas são uma oportunidade de melhoria — responda com empatia',
                    'A nota média é exibida automaticamente no seu perfil público',
                ],
            },
        ],
    },

    '/p/financeiro': {
        icon: '💰',
        title: 'Financeiro do Profissional',
        description: 'Resumo dos seus ganhos, repasses recebidos e histórico financeiro dos atendimentos.',
        sections: [
            {
                heading: 'O que você vê aqui',
                items: [
                    'Total ganho no mês com comparativo ao mês anterior',
                    'Lista de transações: data, cliente, serviço e valor recebido',
                    'Status de repasse: pendente, processando ou pago',
                ],
            },
            {
                heading: 'Como usar',
                items: [
                    'Acompanhe mensalmente para garantir que todos os repasses foram processados',
                    'Use o filtro de período para gerar relatórios de qualquer intervalo',
                    'Em caso de divergência, entre em contato com o suporte informando o ID da transação',
                ],
            },
        ],
    },
};

// Verifica se um path bate com um padrão.
// Segmento wildcard: /produtos/{*}/edit bate com /produtos/123/edit
// Prefixo wildcard: /member-builder* bate com qualquer subpath
function matchPattern(path, pattern) {
    if (path === pattern) return true;
    // Prefixo: /member-builder* bate com /member-builder/qualquer-coisa
    if (pattern.endsWith('*')) {
        return path.startsWith(pattern.slice(0, -1));
    }
    // Wildcard de segmento: /produtos/*/edit bate com /produtos/123/edit
    if (pattern.includes('*')) {
        const pathSegs = path.split('/').filter(Boolean);
        const patSegs  = pattern.split('/').filter(Boolean);
        if (pathSegs.length !== patSegs.length) return false;
        return patSegs.every((seg, i) => seg === '*' || seg === pathSegs[i]);
    }
    // Prefixo exato de subpasta: /produtos bate com /produtos/alunos
    return path.startsWith(pattern + '/');
}

/**
 * Retorna o conteúdo de ajuda para a URL atual.
 * Faz matching pelo padrão mais específico (mais longo) primeiro.
 */
export function getPageHelp(url) {
    const path = url.split('?')[0];
    const sorted = Object.keys(HELP).sort((a, b) => b.length - a.length);
    for (const pattern of sorted) {
        if (matchPattern(path, pattern)) return HELP[pattern];
    }
    return null;
}
