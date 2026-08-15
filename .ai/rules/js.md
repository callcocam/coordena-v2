---
paths:
  - 'resources/js/**'
---

# Js

## useT() retorna objeto, não a função t
O composable @/composables/useT retorna { t, locale }. Sempre desestruturar: `const { t } = useT();`. Usar `const t = useT()` compila normalmente, mas quebra em runtime no render com "TypeError: t(...) is not a function".
