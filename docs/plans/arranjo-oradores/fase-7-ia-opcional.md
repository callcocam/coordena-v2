# Fase 7 — IA assistiva (opcional, por último)

Depende de: fases 2 e 4 estáveis em uso real. Tamanho: M. **Leia antes: `00-INDICE.md`**
(regra inviolável 1: IA nunca confirma nada sozinha).

## Por que por último (lição do v1)

No v1 a IA entrou cedo, virou o eixo do fluxo e falhou junto com ele. Aqui o sistema já está
**completo e operante sem IA** (fases 0–6). A IA entra somente como aceleradora de digitação
em pontos onde o humano já revisa na tela — se a fase 7 nunca acontecer, nada quebra.

## Escopo único: pré-preenchimento da mesa de trabalho

Quando uma resposta de parceira chega (handler 3 da fase 4 / texto colado na fase 2), um job
opcional roda extração estruturada sobre o texto:

```
entrada: corpo íntegro + contexto (semanas em falta do convite, catálogo de esboços,
         oradores já conhecidos da congregação)
saída:   ofertas draft sugeridas { nome, temas[], semana?, telefone?, confiança }
```

- [ ] `ExchangeReplyExtractor` atrás de interface; driver 1: `NullExtractor` (não faz nada);
  driver 2: LLM (Claude API — modelo configurável, structured output). Config
  `public_talks.reply_extractor` default `null` — **opt-in por env**.
- [ ] O resultado vira ofertas `draft` marcadas `suggested_by_ai` + score; a mesa de trabalho
  (fase 2) as mostra **pré-preenchidas e destacadas** ("sugerido — confira"), com a mensagem
  original sempre visível ao lado. Aceitar/editar/descartar é humano; `ExchangeConfirmer`
  continua o único caminho de confirmação.
- [ ] Nome sem match em orador existente da congregação → sugestão de criação (mesmo atalho
  inline da fase 2), nunca criação automática.
- [ ] Falha/timeout/baixa confiança do extractor → mesa abre vazia, como sempre. Zero caminho
  novo de erro para o usuário.

## Guard-rails

- IA **nunca**: confirma oferta, cria orador, envia mensagem, escolhe congregação.
- Log da extração (entrada/saída/custo) para auditoria.
- Métrica simples: % de sugestões aceitas sem edição — se baixa, desligar sem dó.

## Testes

- [ ] Extractor fake nos Feature tests: sugestões aparecem como draft destacado; confirmar
  passa pelo fluxo normal; extractor quebrado → mesa vazia funcional; `NullExtractor` =
  comportamento idêntico à fase 2.
- [ ] Nenhum teste das fases 0–6 alterado por esta fase (prova de que é acoplamento zero).

Rodar `vendor/bin/pint --dirty --format agent`.

## Critério de aceite

- Com extractor ligado: resposta real de parceira abre a mesa já sugerida; coordenador só
  confere e confirma.
- Com extractor desligado ou quebrado: sistema idêntico ao da fase 2.
