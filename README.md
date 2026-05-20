# MINIBANKING API

## Exposed api

### Movimenti

1. GET `/account/{id_account}/transaction` per ottenere l'elenco dei movimenti
2. GET `/account/{id_account}/transaction/{id}` per ottenere il dettaglio di un movimento
3. POST `/account/{id_account}/deposit` per registrare un deposito
4. POST `/account/{id_account}/withdrawal` per registrare un prelievo
5. PUT `/account/{id_account}/transaction/{id}` per modificare la descrizione di un movimento
6. DELETE `/account/{id_account}/transaction/{id}` per eliminare un movimento secondo la regola scelta.
Pu'o essere cancellata solo l'ultima transazione dell'account

### Saldo

7. GET `/account/{id_account}/balance` per ottenere il saldo attuale

### Conversione del saldo

8. GET `/account/{id_account}/balance/convert/fiat?to=USD` per convertire il saldo in una valuta fiat
9. GET `/account/{id_account}/balance/convert/crypto?to=BTC` per convertire il saldo in una criptovaluta

### Invio dati

Per inviare dati per la creazione o la modifica di una transazione, essi devono essere inseriti in JSON nel body della richiesta.

I campi monimi sono `amount` e `description`.

## Su Windows
# Clonare il progetto nella cartella htdocs di xampp e eseguire il comando
 `compose installer`
# Successivamente avviare apache e mysql
# Andare su phpmyadmin e incollare lo script di init.sql


## Testing:

# ACCOUNT
curl -X GET http://localhost:8000/account

# CURRENCIES
curl -X GET http://localhost:8000/currency

# TRANSACTIONS SUMMARY
curl -X GET http://localhost:8000/trans

# BALANCE
curl -X GET http://localhost:8000/account/1/balance

# CONVERT FIAT
curl -X GET "http://localhost:8000/account/1/balance/convert/fiat?to=USD"

# GET ALL TRANSACTIONS
curl -X GET http://localhost:8000/account/1/transaction

# GET SINGLE TRANSACTION
curl -X GET http://localhost:8000/account/1/transaction/1

# DEPOSIT
curl -X POST http://localhost:8000/account/1/deposit \
-H "Content-Type: application/json" \
-d '{"amount":100,"description":"deposit test"}'

# WITHDRAWAL
curl -X POST http://localhost:8000/account/1/withdrawal \
-H "Content-Type: application/json" \
-d '{"amount":50,"description":"withdraw test"}'

# EDIT DESCRIPTION
curl -X PUT http://localhost:8000/account/1/transaction/1 \
-H "Content-Type: application/json" \
-d '{"description":"updated description"}'

# DELETE LAST TRANSACTION
curl -X DELETE http://localhost:8000/account/1/transaction/1

# ERROR TEST - INVALID ACCOUNT
curl -X GET http://localhost:8000/account/9999/balance

# ERROR TEST - NEGATIVE DEPOSIT
curl -X POST http://localhost:8000/account/1/deposit \
-H "Content-Type: application/json" \
-d '{"amount":-10}'
