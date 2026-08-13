<?php

declare(strict_types=1);

// Conteúdo jurídico das páginas públicas → app.legal.*
// Redigido conforme a LGPD (Lei 13.709/2018), o Marco Civil da Internet
// (Lei 12.965/2014) e o Código de Defesa do Consumidor (Lei 8.078/1990).
// Estrutura: cada documento tem "sections" (array), e cada seção tem
// "id", "title", "body" (parágrafos) e, opcionalmente, "list" (itens).

return [

    'common' => [
        'toc_title' => 'Nesta página',
        'updated_label' => 'Atualizado em',
        'updated_at' => '5 de julho de 2026',
        'back_home' => 'Voltar ao início',
        'reading_note' => 'Leva cerca de :minutes minutos para ler.',
    ],

    'terms' => [
        'meta_title' => 'Termos de Uso',
        'meta_description' => 'Condições para usar o Coordena: cadastro, responsabilidades, dados de voluntários e regras do serviço.',
        'eyebrow' => 'Documento legal',
        'title' => 'Termos de Uso',
        'intro' => 'Estes Termos regulam o uso do Coordena. Ao criar uma conta ou usar a plataforma, você concorda com as condições abaixo. Leia com atenção.',

        'sections' => [
            [
                'id' => 'aceitacao',
                'title' => '1. Aceitação dos Termos',
                'body' => [
                    'Ao acessar ou usar o Coordena ("plataforma", "serviço"), você declara ter lido, entendido e aceito estes Termos de Uso e a nossa Política de Privacidade.',
                    'Se você usa o Coordena em nome de uma equipe, congregação ou organização, declara ter autoridade para aceitar estes Termos em nome dela.',
                    'Caso não concorde com qualquer condição, não utilize o serviço.',
                ],
            ],
            [
                'id' => 'servico',
                'title' => '2. O que é o Coordena',
                'body' => [
                    'O Coordena é uma ferramenta para organizar equipes de voluntários: cadastrar pessoas, montar escalas e programações de eventos e confirmar a presença de cada voluntário, inclusive por meio de mensagens de WhatsApp.',
                    'O serviço é oferecido como está e pode evoluir com novos recursos, ajustes ou remoção de funcionalidades ao longo do tempo.',
                ],
            ],
            [
                'id' => 'conta',
                'title' => '3. Cadastro e conta',
                'body' => [
                    'Para usar a maior parte dos recursos, é necessário criar uma conta com informações verdadeiras, completas e atualizadas.',
                    'Você é responsável por manter a confidencialidade das suas credenciais e por toda atividade realizada na sua conta. Avise-nos imediatamente em caso de uso não autorizado.',
                ],
            ],
            [
                'id' => 'uso-aceitavel',
                'title' => '4. Uso aceitável',
                'body' => [
                    'Você concorda em usar o Coordena apenas para fins legítimos de coordenação de equipes e eventos. É proibido:',
                ],
                'list' => [
                    'enviar mensagens não solicitadas (spam) ou conteúdo ilícito, ofensivo ou enganoso;',
                    'cadastrar contatos de pessoas sem base legal ou consentimento adequado para tratá-los;',
                    'tentar acessar áreas, contas ou dados de terceiros sem autorização;',
                    'sobrecarregar, comprometer ou tentar burlar a segurança da plataforma;',
                    'usar o serviço para violar direitos de terceiros ou a legislação vigente.',
                ],
            ],
            [
                'id' => 'dados-voluntarios',
                'title' => '5. Dados de voluntários e terceiros',
                'body' => [
                    'Ao cadastrar voluntários ou outros contatos, você atua como controlador desses dados pessoais, nos termos da LGPD (Lei 13.709/2018). O Coordena atua como operador, tratando os dados conforme suas instruções e apenas para viabilizar o serviço.',
                    'É sua responsabilidade ter uma base legal para tratar esses dados (por exemplo, consentimento ou legítimo interesse) e informar aos titulares como as informações serão usadas.',
                    'Você se compromete a atender às solicitações dos titulares e a manter os dados atualizados e mínimos ao necessário.',
                ],
            ],
            [
                'id' => 'whatsapp',
                'title' => '6. Mensagens e WhatsApp',
                'body' => [
                    'O Coordena pode enviar mensagens de WhatsApp em seu nome para confirmar presença e comunicar informações da escala.',
                    'Você é responsável pelo conteúdo enviado e por garantir que os destinatários concordaram em receber essas mensagens. O uso do WhatsApp também está sujeito aos termos da Meta Platforms, Inc.',
                ],
            ],
            [
                'id' => 'planos',
                'title' => '7. Planos e pagamentos',
                'body' => [
                    'O Coordena pode oferecer recursos gratuitos e planos pagos. Quando houver cobrança, as condições, valores e a forma de pagamento serão informados de forma clara antes da contratação.',
                    'Nos termos do Código de Defesa do Consumidor, contratações feitas fora do estabelecimento comercial podem ser canceladas em até 7 (sete) dias a partir da adesão.',
                ],
            ],
            [
                'id' => 'propriedade',
                'title' => '8. Propriedade intelectual',
                'body' => [
                    'A marca, o software, o design e os demais elementos do Coordena pertencem aos seus criadores e são protegidos por lei. Estes Termos não transferem a você nenhum direito sobre eles, além do uso da plataforma conforme aqui previsto.',
                    'O conteúdo que você cadastra (voluntários, eventos, escalas) continua sendo seu. Você nos concede apenas a autorização necessária para hospedar e processar esse conteúdo e prestar o serviço.',
                ],
            ],
            [
                'id' => 'suspensao',
                'title' => '9. Suspensão e encerramento',
                'body' => [
                    'Você pode encerrar sua conta a qualquer momento. Podemos suspender ou encerrar o acesso em caso de violação destes Termos, uso indevido ou exigência legal, buscando avisar você quando possível.',
                    'Após o encerramento, seus dados serão tratados conforme a Política de Privacidade e os prazos legais de retenção.',
                ],
            ],
            [
                'id' => 'responsabilidade',
                'title' => '10. Limitação de responsabilidade',
                'body' => [
                    'O Coordena é oferecido sem garantia de disponibilidade ininterrupta. Buscamos manter o serviço estável, mas não nos responsabilizamos por indisponibilidades causadas por terceiros, força maior ou falhas de conexão.',
                    'Na máxima extensão permitida pela lei, não respondemos por danos indiretos decorrentes do uso ou da impossibilidade de uso do serviço. Nada nestes Termos afasta direitos previstos em normas de ordem pública, como o Código de Defesa do Consumidor.',
                ],
            ],
            [
                'id' => 'alteracoes',
                'title' => '11. Alterações destes Termos',
                'body' => [
                    'Podemos atualizar estes Termos para refletir mudanças no serviço ou na legislação. Quando a alteração for relevante, avisaremos por meios razoáveis. O uso continuado após a atualização indica sua concordância com a nova versão.',
                ],
            ],
            [
                'id' => 'foro',
                'title' => '12. Lei aplicável e foro',
                'body' => [
                    'Estes Termos são regidos pelas leis da República Federativa do Brasil. Fica eleito o foro do domicílio do usuário para dirimir controvérsias, conforme o Código de Defesa do Consumidor, quando aplicável.',
                ],
            ],
            [
                'id' => 'contato',
                'title' => '13. Fale com a gente',
                'body' => [
                    'Dúvidas sobre estes Termos? Entre em contato pelo e-mail contato@coordena.space.',
                ],
            ],
        ],
    ],

    'privacy' => [
        'meta_title' => 'Política de Privacidade',
        'meta_description' => 'Como o Coordena coleta, usa, compartilha e protege dados pessoais, conforme a LGPD.',
        'eyebrow' => 'Documento legal',
        'title' => 'Política de Privacidade',
        'intro' => 'Sua privacidade importa. Esta Política explica, de forma clara, quais dados o Coordena trata, por que, com quem compartilha e quais são os seus direitos — conforme a LGPD (Lei 13.709/2018).',

        'sections' => [
            [
                'id' => 'controlador',
                'title' => '1. Quem trata seus dados',
                'body' => [
                    'O Coordena é o responsável pelo tratamento dos dados de cadastro dos usuários da plataforma (nome, e-mail e informações da conta).',
                    'Em relação aos dados de voluntários e contatos que você cadastra, você é o controlador e o Coordena atua como operador, tratando essas informações conforme suas instruções.',
                ],
            ],
            [
                'id' => 'dados-coletados',
                'title' => '2. Dados que coletamos',
                'body' => [
                    'Coletamos apenas o necessário para o serviço funcionar:',
                ],
                'list' => [
                    'Dados de cadastro: nome, e-mail e senha (armazenada de forma criptografada).',
                    'Dados de voluntários: nome, telefone e informações da escala que você inserir.',
                    'Dados de uso: registros de acesso e ações na plataforma, mantidos conforme o Marco Civil da Internet.',
                    'Dados técnicos: endereço IP, tipo de dispositivo e navegador, para segurança e funcionamento.',
                ],
            ],
            [
                'id' => 'finalidades',
                'title' => '3. Por que usamos seus dados',
                'body' => [
                    'Tratamos dados pessoais com base nas hipóteses legais do art. 7º da LGPD, para as seguintes finalidades:',
                ],
                'list' => [
                    'permitir seu cadastro, autenticação e uso da plataforma (execução de contrato);',
                    'montar escalas e enviar confirmações de presença, inclusive por WhatsApp (execução de contrato e legítimo interesse);',
                    'garantir segurança, prevenir fraudes e cumprir obrigações legais;',
                    'melhorar o serviço e prestar suporte (legítimo interesse).',
                ],
            ],
            [
                'id' => 'compartilhamento',
                'title' => '4. Com quem compartilhamos',
                'body' => [
                    'Não vendemos seus dados. Compartilhamos apenas com prestadores que ajudam a operar o serviço, sob obrigação de confidencialidade e segurança:',
                ],
                'list' => [
                    'provedores de hospedagem e infraestrutura em nuvem;',
                    'serviços de envio de mensagens de WhatsApp, para entregar as confirmações;',
                    'autoridades públicas, quando houver obrigação legal ou ordem judicial.',
                ],
            ],
            [
                'id' => 'cookies',
                'title' => '5. Cookies e tecnologias',
                'body' => [
                    'Usamos cookies e armazenamento local essenciais para manter você conectado e lembrar preferências, como o tema claro ou escuro. Sem eles, partes da plataforma não funcionam corretamente.',
                    'Você pode limpar ou bloquear cookies nas configurações do navegador, ciente de que isso pode afetar sua experiência.',
                ],
            ],
            [
                'id' => 'seguranca',
                'title' => '6. Segurança',
                'body' => [
                    'Adotamos medidas técnicas e organizacionais para proteger os dados, como criptografia de senhas, conexão segura (HTTPS) e controle de acesso.',
                    'Nenhum sistema é totalmente imune a incidentes. Caso ocorra um incidente de segurança relevante, comunicaremos os titulares e a Autoridade Nacional de Proteção de Dados (ANPD) conforme a lei.',
                ],
            ],
            [
                'id' => 'retencao',
                'title' => '7. Por quanto tempo guardamos',
                'body' => [
                    'Mantemos os dados pelo tempo necessário às finalidades desta Política ou para cumprir obrigações legais. Registros de acesso a aplicações são guardados por, no mínimo, 6 (seis) meses, conforme o Marco Civil da Internet.',
                    'Encerrada a conta, os dados são eliminados ou anonimizados, salvo quando a lei exigir retenção.',
                ],
            ],
            [
                'id' => 'direitos',
                'title' => '8. Seus direitos',
                'body' => [
                    'Como titular de dados, você pode, a qualquer momento, exercer os direitos garantidos pelo art. 18 da LGPD:',
                ],
                'list' => [
                    'confirmar a existência de tratamento e acessar seus dados;',
                    'corrigir dados incompletos, inexatos ou desatualizados;',
                    'solicitar anonimização, bloqueio ou eliminação de dados desnecessários;',
                    'solicitar a portabilidade dos dados;',
                    'revogar o consentimento e se opor a tratamentos, quando aplicável.',
                ],
            ],
            [
                'id' => 'voluntarios',
                'title' => '9. Dados de voluntários',
                'body' => [
                    'Os voluntários e contatos que você cadastra são tratados por você como controlador. Se você é um voluntário e quer exercer seus direitos, fale primeiro com a equipe ou congregação que cadastrou seus dados.',
                    'O Coordena, como operador, apoia o controlador no atendimento a essas solicitações.',
                ],
            ],
            [
                'id' => 'caravanas',
                'title' => '10. Dados de participantes de caravanas',
                'body' => [
                    'Ao se inscrever em uma caravana pelo link público de uma congregação, você informa nome, sobrenome, RG (documento) e órgão emissor, telefone e, opcionalmente, data de nascimento.',
                    'Esses dados são coletados exclusivamente para montar a lista de passageiros exigida na fiscalização do transporte fretado (identificação de cada ocupante do veículo) e para organizar embarque e pagamento. Não são usados para nenhuma outra finalidade.',
                    'O acesso é restrito a quem organiza o transporte: o capitão responsável pela congregação enxerga apenas os participantes da própria congregação, e o superintendente que criou a caravana enxerga o conjunto do arranjo. Os relatórios em PDF com esses dados usam links temporários, que expiram em poucos dias.',
                    'Como controlador desses dados (é a congregação, por meio de quem organiza a caravana, que decide coletá-los para viabilizar a viagem), aplicam-se também as regras da seção anterior ("9. Dados de voluntários"). Encerrada a caravana, os dados podem ser removidos a pedido do titular ou do controlador, salvo exigência legal de retenção pela empresa de transporte.',
                ],
            ],
            [
                'id' => 'transferencia',
                'title' => '11. Transferência internacional',
                'body' => [
                    'Alguns prestadores podem processar dados em servidores fora do Brasil. Nesses casos, exigimos garantias de proteção compatíveis com a LGPD.',
                ],
            ],
            [
                'id' => 'menores',
                'title' => '12. Crianças e adolescentes',
                'body' => [
                    'O Coordena não é destinado ao uso direto por menores de 18 anos sem supervisão. O cadastro de menores como voluntários deve respeitar o melhor interesse e o consentimento de pais ou responsáveis, conforme a LGPD.',
                ],
            ],
            [
                'id' => 'encarregado',
                'title' => '13. Encarregado e contato',
                'body' => [
                    'Para exercer seus direitos ou tirar dúvidas sobre privacidade, fale com o nosso Encarregado de Dados (DPO) pelo e-mail contato@coordena.space.',
                    'Responderemos às solicitações nos prazos previstos na legislação.',
                ],
            ],
            [
                'id' => 'alteracoes',
                'title' => '14. Alterações desta Política',
                'body' => [
                    'Podemos atualizar esta Política para refletir mudanças no serviço ou na legislação. Publicaremos a nova versão nesta página com a data de atualização e, quando a mudança for relevante, avisaremos você.',
                ],
            ],
        ],
    ],
];
