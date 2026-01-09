document.addEventListener('DOMContentLoaded', function() {
    
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
    const numerosContaveis = [712, 183, 528]; // Example target numbers
    const elementosNumeros = [document.querySelector('#numero-contavel-1'), document.querySelector('#numero-contavel-2'), document.querySelector('#numero-contavel-3')];
    
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
});
