# 📊 Novo Sistema de Histórico de Atualizações de Pragas

## ✅ O que mudou:

### Antes (❌ Problema):
- Ao atualizar uma praga, o registro **era sobrescrito**
- Perdia o histórico de evolução
- Gráfico só tinha 1 ponto (última atualização)

### Agora (✅ Solução):
- Cada atualização **cria um novo registro** na tabela
- **Mantém o histórico completo** de todas as atualizações
- Gráfico mostra **evolução em linha** das pragas

---

## 🔄 Como funciona agora:

### 1️⃣ **Primeira Vez (Cadastro)**
```
tabela Pragas_Surtos:
ID=1, Nome=Lagarta, media_pragas_planta=NULL, ID_Praga_Original=NULL
```

### 2️⃣ **Primeira Atualização**
```
tabela Pragas_Surtos:
ID=1, Nome=Lagarta, media_pragas_planta=NULL, ID_Praga_Original=NULL  (ORIGINAL)
ID=2, Nome=Lagarta, media_pragas_planta=5.5, ID_Praga_Original=1     (1ª ATUALIZAÇÃO)
```

### 3️⃣ **Segunda Atualização**
```
tabela Pragas_Surtos:
ID=1, Nome=Lagarta, media_pragas_planta=NULL, ID_Praga_Original=NULL  (ORIGINAL)
ID=2, Nome=Lagarta, media_pragas_planta=5.5, ID_Praga_Original=1     (1ª ATUALIZAÇÃO)
ID=3, Nome=Lagarta, media_pragas_planta=8.0, ID_Praga_Original=1     (2ª ATUALIZAÇÃO)
```

---

## 📈 Gráfico agora mostra:

```
      │
   10 │                    ●
      │                   / \
    8 │          ●       /   \
      │         / \     /     
    6 │        /   \   /
      │       /     \ /
    4 │      /
      │     /
    2 │    /
      │   /
    0 │__●__________________________
      Data     Hora1  Hora2  Hora3
```

- **Ponto 1**: Primeira atualização (5.5 pragas)
- **Ponto 2**: Segunda atualização (8.0 pragas) - VERMELHO = aumento
- **Ponto 3**: Terceira atualização (6.0 pragas) - VERDE = queda

---

## 🧪 Como testar:

### Passo 1: Cadastrar uma praga
1. Vá em **Cadastro de Praga**
2. Preencha os dados básicos
3. **NÃO preencha** "Média de Pragas por Planta" (vai ter NULL)
4. Clique em **Cadastrar**

### Passo 2: Primeira Atualização
1. Vá em **Minhas Pragas** > clique em **Atualizar**
2. Preencha **"Média de Pragas por Planta": 5.0**
3. Clique em **Atualizar Praga**
4. Observe: **Um registro novo é criado** (não sobrescreve o anterior)

### Passo 3: Segunda Atualização
1. Vá em **Minhas Pragas** > clique em **Atualizar** (mesma praga)
2. Mude para **"Média de Pragas por Planta": 8.0**
3. Clique em **Atualizar Praga**
4. Agora há **2 registros** na tabela

### Passo 4: Ver o gráfico
1. Vá ao **Dashboard**
2. Procure o gráfico **"Evolução da Infestação"**
3. Deve aparecer uma **LINHA conectando os 2 pontos**
4. O gráfico mostra a **evolução**: começou em 5.0 e subiu para 8.0

### Passo 5: Terceira Atualização (Teste de Queda)
1. Atualize novamente para **"Média": 6.0**
2. No gráfico, deve aparecer um **ponto VERDE** (queda de 8 para 6)
3. A linha mostra a evolução: 5.0 → 8.0 → 6.0

---

## 🎨 Cores no gráfico:

| Cor | Significado | Exemplo |
|-----|-------------|---------|
| 🔴 Vermelho | Aumento de pragas | 5 → 8 |
| 🟢 Verde | Redução de pragas | 8 → 6 |
| 🟡 Amarelo | Sem mudança | 5 → 5 |
| ⬛ Preto | Primeira atualização | - |

---

## 📊 Banco de dados - Estrutura:

### Tabela `Pragas_Surtos` agora tem:

| Campo | Tipo | Função |
|-------|------|--------|
| ID | INT | Identificador único de cada registro (inclui atualizações) |
| Nome | VARCHAR | Nome da praga |
| media_pragas_planta | DECIMAL | Média de pragas por planta |
| Data_Aparicao | DATETIME | Data/hora **de cada atualização** |
| ID_Praga_Original | INT | **Agrupa todas as atualizações da mesma praga original** |
| ID_Usuario | INT | Usuário proprietário |

---

## 🔍 Exemplos SQL:

### Ver todos os registros de uma praga (histórico completo):
```sql
SELECT ID, Data_Aparicao, media_pragas_planta 
FROM Pragas_Surtos 
WHERE ID_Praga_Original = 1 
   OR (ID_Praga_Original IS NULL AND ID = 1)
ORDER BY Data_Aparicao ASC;
```

### Contar quantas atualizações uma praga teve:
```sql
SELECT COUNT(*) as total_atualizacoes 
FROM Pragas_Surtos 
WHERE ID_Praga_Original = 1;
```

### Ver a evolução (aumentos e quedas):
```sql
SELECT 
  Data_Aparicao,
  media_pragas_planta,
  LAG(media_pragas_planta) OVER (ORDER BY Data_Aparicao) as valor_anterior,
  CASE 
    WHEN media_pragas_planta > LAG(media_pragas_planta) OVER (ORDER BY Data_Aparicao) THEN 'AUMENTO'
    WHEN media_pragas_planta < LAG(media_pragas_planta) OVER (ORDER BY Data_Aparicao) THEN 'QUEDA'
    ELSE 'IGUAL'
  END as mudanca
FROM Pragas_Surtos 
WHERE ID_Praga_Original = 1 
   OR (ID_Praga_Original IS NULL AND ID = 1)
ORDER BY Data_Aparicao;
```

---

## ⚠️ Importante:

- ✅ Cada atualização é um **novo registro**
- ✅ O ID original é **preservado** em `ID_Praga_Original`
- ✅ O gráfico agrupa por praga automaticamente
- ✅ **Não perde histórico** - tudo fica salvo
- ❌ **NÃO sobrescreve** registros antigos

---

Agora ao atualizar uma praga, você verá toda a evolução no gráfico! 🎉
