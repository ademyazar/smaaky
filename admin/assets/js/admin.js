document.querySelectorAll('.has-submenu > a').forEach(item => {
    item.addEventListener('click', function(e){
        e.preventDefault();
        this.parentElement.classList.toggle('active');
    });
});