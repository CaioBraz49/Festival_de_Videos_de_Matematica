<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contato por Botões</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5 text-center">
        <h2 class="mb-4">Escolha o destinatário:</h2>
        
        <!-- Botão 1 -->
        <button class="btn btn-primary mx-2" data-bs-toggle="modal" data-bs-target="#contactModal"
                data-email="valcibalbino@gmail.com" data-nome="Gmail Valci">
            <i class="bi bi-briefcase"></i> Gmail Valci
        </button>
        
        <!-- Botão 2 -->
        <button class="btn btn-success mx-2" data-bs-toggle="modal" data-bs-target="#contactModal"
                data-email="suporte@empresa.com" data-nome="Suporte Técnico">
            <i class="bi bi-headset"></i> Suporte
        </button>
        
        <!-- Botão 3 -->
        <button class="btn btn-warning mx-2" data-bs-toggle="modal" data-bs-target="#contactModal"
                data-email="rh@empresa.com" data-nome="Recursos Humanos">
            <i class="bi bi-people"></i> RH
        </button>
    </div>

    <!-- Modal de Contato -->
    <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel">Enviar Mensagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="contactForm" method="post" action="mail.php">
                    <div class="modal-body">
                        <!-- Destinatário -->
                        <div class="input-group mb-3">
                            <span class="input-group-text">Para:</span>
                            <input type="text" class="form-control" id="modalDestinatario" readonly>
                        </div>
                        
                        <!-- Email -->
                        <div class="input-group mb-3">
                            <span class="input-group-text">Email:</span>
                            <input type="email" class="form-control" id="modalEmail" name="email" readonly>
                        </div>
                        
                        <!-- Assunto -->
                        <div class="input-group mb-3">
                            <span class="input-group-text">Assunto:</span>
                            <input type="text" class="form-control" id="assunto" name="assunto" required>
                        </div>
                        
                        <!-- Mensagem -->
                        <div class="input-group mb-3">
                            <span class="input-group-text">Mensagem:</span>
                            <textarea class="form-control" id="mensagem" name="mensagem" rows="3" required></textarea>
                        </div>
                        
                        <input type="hidden" id="modalNome" name="nome">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Enviar Mensagem
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS e dependências -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script para manipular o modal -->
    <script>
        const contactModal = document.getElementById('contactModal');
        
        contactModal.addEventListener('show.bs.modal', function(event) {
            // Botão que acionou o modal
            const button = event.relatedTarget;
            
            // Extrair dados dos atributos data-*
            const email = button.getAttribute('data-email');
            const nome = button.getAttribute('data-nome');
            
            // Atualizar o conteúdo do modal
            document.getElementById('modalDestinatario').value = nome;
            document.getElementById('modalEmail').value = email;
            document.getElementById('modalNome').value = nome;
            
            // Focar no campo de assunto quando o modal abrir
            setTimeout(() => {
                document.getElementById('assunto').focus();
            }, 500);
        });
        
        // Limpar formulário quando o modal for fechado
        contactModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('contactForm').reset();
        });
    </script>
</body>
</html>