document.addEventListener('DOMContentLoaded', function() {
    
    // Generate CSRF token on page load
    function generateCSRFToken() {
        // Create a random token (64 character hex string)
        let token = '';
        const chars = '0123456789abcdef';
        for (let i = 0; i < 64; i++) {
            token += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return token;
    }
    
    // Set CSRF token for both forms
    const csrfToken = generateCSRFToken();
    const csrfTokenContato = document.getElementById('csrf_token_contato');
    const csrfTokenTrabalhe = document.getElementById('csrf_token_trabalhe');
    
    if (csrfTokenContato) {
        csrfTokenContato.value = csrfToken;
    }
    if (csrfTokenTrabalhe) {
        csrfTokenTrabalhe.value = csrfToken;
    }

    // Match height and width of decoracao_dobra to conteudo_dobra
    function matchDecoraoHeights() {
        const decoracaoDobras = document.querySelectorAll('.decoracao_dobra');
        decoracaoDobras.forEach(decoracao => {
            const conteudoDobra = decoracao.nextElementSibling;
            if (conteudoDobra && conteudoDobra.classList.contains('conteudo_dobra')) {
                let height = window.getComputedStyle(conteudoDobra).getPropertyValue('height');
                decoracao.style.height = height;
            }
        });
        const imagensFundo = document.querySelectorAll('.imagem-fundo-dobra');
        imagensFundo.forEach(imagem => {
            if (imagem.classList.contains('dobra-parallax')) return;
            const urlIimagem = imagem.getAttribute('data-foto');
            imagem.style.backgroundImage = `url(${urlIimagem})`;
            imagem.style.backgroundSize = 'cover';
            imagem.style.backgroundPosition = 'center';
        });
        const imagensDentroDobraParallax = document.querySelectorAll('.dobra-parallax img');
        imagensDentroDobraParallax.forEach(imagem => {
            imagem.parentElement.style.position = 'relative';
            imagem.style.position = 'absolute';
            imagem.style.top = '50%';
            imagem.style.left = '50%';
            imagem.style.transform = 'translate(-50%, -50%)';
            imagem.style.marginTop = '0';
        });
    }
    
    // Call on load
    matchDecoraoHeights();
    
    // Call again on window resize
    window.addEventListener('resize', matchDecoraoHeights);
    
    function adjustParallax() {
        const parallaxDivs = document.querySelectorAll('.dobra-parallax');
        parallaxDivs.forEach(div => {
            const urlIimagem = div.getAttribute('data-foto');
            const imgInterna = div.querySelector('img');
            
            if (imgInterna) {
                const urlImgInterna = imgInterna.src;
                const size = window.innerWidth < 768 ? '33%' : '15%';
                div.style.setProperty('background-image', `url("${urlImgInterna}"), url("${urlIimagem}")`, 'important');
                div.style.setProperty('background-size', `${size} auto, cover`, 'important');
                div.style.setProperty('background-position', 'center center, center center', 'important');
                div.style.setProperty('background-attachment', 'scroll, fixed', 'important');
                div.style.setProperty('background-repeat', 'no-repeat, no-repeat', 'important');
                imgInterna.style.display = 'none';
            } else {
                div.style.backgroundImage = `url(${urlIimagem})`;
                div.style.backgroundSize = 'cover';
                div.style.backgroundPosition = 'center';
                div.style.backgroundAttachment = 'fixed';
            }
        });
    }
    adjustParallax();
    window.addEventListener('resize', adjustParallax);

    // Recalculate layout after all images are fully loaded
    window.addEventListener('load', function() {
        matchDecoraoHeights();
        adjustParallax();
    });

    // Image modal functionality
    const modal = document.getElementById('modal_imagem');
    const closeBtn = document.getElementById('close_modal_imagem');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    // Add click events to images to open modal
    const images = document.querySelectorAll('.img_mosaico');
    images.forEach(img => {
        img.addEventListener('click', function() {
            const modalImg = document.getElementById('img_modal');
            modalImg.src = this.src;
            if (window.innerWidth >= 768) {
                modal.style.display = 'block';
            }
        });
    });
    // Hide/show WhatsApp button based on rodapé visibility
    const botaoWhatsapp = document.getElementById('botao_flutuante_whatsapp');
    const rodape = document.querySelector('.rodape');
    
    if (botaoWhatsapp && rodape) {
        const rodapeObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    botaoWhatsapp.style.visibility = 'hidden';
                } else {
                    botaoWhatsapp.style.visibility = 'visible';
                }
            });
        });
        
        rodapeObserver.observe(rodape);
    }
    
    const numerosContaveis = [35, 100, 150, 2500, 6000]; // Example target numbers
    const elementosNumeros = [document.querySelector('#numero-contavel-1'), document.querySelector('#numero-contavel-2'), document.querySelector('#numero-contavel-3'), document.querySelector('#numero-contavel-4'), document.querySelector('#numero-contavel-5')];
    
    elementosNumeros.forEach((elemento, index) => {
        if (!elemento) return; // Skip if element doesn't exist
        
        let hasStarted = false;
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !hasStarted) {
                    hasStarted = true;
                    let count = 0;
                    const target = numerosContaveis[index];
                    const increment = Math.ceil(target / 100); // Adjust speed here
                    const interval = setInterval(() => {
                        count += increment;
                        if (count >= target) {
                            count = target;
                            clearInterval(interval);
                        }
                        elemento.innerHTML = count;
                    }, 30); // Update every 30ms
                }
            });
        });
        
        observer.observe(elemento);
    });
    
    // Contact form submission
    const formContato = document.getElementById('form_contato');
    if (formContato) {
        const btEnviarContato = document.getElementById('bt_enviar_contato');
        
        if (btEnviarContato) {
            formContato.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const nome = document.getElementById('nome_contato').value;
                const email = document.getElementById('email_contato').value;
                const mensagem = document.getElementById('mensagem_contato').value;
                const csrf_token = document.getElementById('csrf_token_contato').value;
                
                // Simple validation
                if (!nome || !email || !mensagem) {
                    alert('Por favor, preencha todos os campos');
                    return;
                }
                
                // Create FormData
                const formData = new FormData();
                formData.append('form_type', 'contato');
                formData.append('nome', nome);
                formData.append('email', email);
                formData.append('mensagem', mensagem);
                formData.append('csrf_token', csrf_token);
                
                // Disable button while sending
                btEnviarContato.disabled = true;
                const originalText = btEnviarContato.textContent;
                btEnviarContato.textContent = 'Enviando...';
                
                // Send request
                fetch('send_email.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Email enviado com sucesso! Obrigado pelo contato.');
                        // Reset form
                        document.getElementById('nome_contato').value = '';
                        document.getElementById('email_contato').value = '';
                        document.getElementById('mensagem_contato').value = '';
                    } else {
                        alert('Erro: ' + (data.message || 'Não foi possível enviar o email'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erro ao enviar o email. Tente novamente mais tarde.');
                })
                .finally(() => {
                    // Re-enable button
                    btEnviarContato.disabled = false;
                    btEnviarContato.textContent = originalText;
                });
            });
        }
    }
    
    // Trabalhe Conosco form submission
    const formTrabalhe = document.getElementById('form_trabalhe');
    if (formTrabalhe) {
        const btEnviarTrabalhe = document.getElementById('bt_enviar_trabalhe');
        
        if (btEnviarTrabalhe) {
            formTrabalhe.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const nome = document.getElementById('nome_trabalhe').value;
                const email = document.getElementById('email_trabalhe').value;
                const telefone = document.getElementById('telefone_trabalhe').value;
                const cargo = document.getElementById('cargo_trabalhe').value;
                const curriculo = document.getElementById('curriculo_trabalhe').files[0];
                const csrf_token = document.getElementById('csrf_token_trabalhe').value;
                
                // Simple validation
                if (!nome || !email || !telefone || !cargo || !curriculo) {
                    alert('Por favor, preencha todos os campos');
                    return;
                }
                
                // Create FormData
                const formData = new FormData();
                formData.append('form_type', 'trabalhe');
                formData.append('nome', nome);
                formData.append('email', email);
                formData.append('telefone', telefone);
                formData.append('cargo', cargo);
                formData.append('curriculo', curriculo);
                formData.append('csrf_token', csrf_token);
                
                // Disable button while sending
                btEnviarTrabalhe.disabled = true;
                const originalText = btEnviarTrabalhe.textContent;
                btEnviarTrabalhe.textContent = 'Enviando...';
                
                // Send request
                fetch('send_email.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Candidatura enviada com sucesso! Obrigado pelo interesse.');
                        // Reset form
                        document.getElementById('nome_trabalhe').value = '';
                        document.getElementById('email_trabalhe').value = '';
                        document.getElementById('telefone_trabalhe').value = '';
                        document.getElementById('cargo_trabalhe').value = '';
                        document.getElementById('curriculo_trabalhe').value = '';
                    } else {
                        alert('Erro: ' + (data.message || 'Não foi possível enviar a candidatura.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erro ao enviar a candidatura. Tente novamente mais tarde.');
                })
                .finally(() => {
                    // Re-enable button
                    btEnviarTrabalhe.disabled = false;
                    btEnviarTrabalhe.textContent = originalText;
                });
            });
        }
    }
});
