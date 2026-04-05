import os
import sys

# Define absolute paths based on script location
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, 'grc.db')

def reset():
    print("=====================================================")
    print("⚠️  ATENÇÃO: ISSO APAGARÁ TODOS OS DADOS DO SISTEMA!  ⚠️")
    print("=====================================================")
    print("Todos os clientes, riscos, incidentes, políticas e")
    print("treinamentos existentes serão deletados para sempre.")
    print("Use isso apenas se quiser uma instalação totalmente limpa.")
    print("")
    resp = input("Deseja continuar? (S/N): ")
    if resp.lower() != 's':
        print("Operação cancelada. Nenhum dado foi apagado.")
        return
        
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
        print("🗑️ Arquivo grc.db deletado com sucesso.")
    else:
        print("ℹ️ Banco de dados anterior não encontrado. Criando um novo do zero.")
    
    # Importa a função init_db
    sys.path.insert(0, BASE_DIR)
    from app.database import init_db
    
    print("🏗️ Recriando as tabelas e inserindo os dados estruturais básicos...")
    init_db()
    
    print("\n✅ Banco de dados zerado com sucesso!")
    print("O usuário admin padrão (ou os que estiverem no seu arquivo .env) já estão configurados.")
    print("Você pode iniciar o servidor normalmente usando o ./deploy.sh ou run.")

if __name__ == "__main__":
    reset()
