document.addEventListener('DOMContentLoaded', function() {
    // Code to run when the DOM is fully loaded
    console.log('DOM fully loaded and parsed');
    
    // Example: Add event listeners or initialize components here
    // For example, modal functionality
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
            modal.style.display = 'block';
        });
    });
});
