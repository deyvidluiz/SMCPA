var menuItem = document.querySelectorAll('.item-menu')

function selectLink() {
    menuItem.forEach(function(item) {
        item.classList.remove('ativo')
    })
    this.classList.add('ativo')
}

menuItem.forEach(function(item) {
    item.addEventListener('click', selectLink)
})

// Menu mobile: hamburger e overlay (só executa se os elementos existirem)
var btnExp = document.querySelector('#btn-exp')
var sidebar = document.querySelector('#sidebar')
var overlay = document.querySelector('#sidebar-overlay')

function toggleMenu() {
    if (sidebar) sidebar.classList.toggle('aberto')
    if (overlay) overlay.classList.toggle('active')
    if (btnExp) btnExp.classList.toggle('aberto')
    document.body.style.overflow = sidebar && sidebar.classList.contains('aberto') ? 'hidden' : ''
}

function fecharMenu() {
    if (sidebar) sidebar.classList.remove('aberto')
    if (overlay) overlay.classList.remove('active')
    if (btnExp) btnExp.classList.remove('aberto')
    document.body.style.overflow = ''
}

if (btnExp) {
    btnExp.addEventListener('click', toggleMenu)
}
if (overlay) {
    overlay.addEventListener('click', fecharMenu)
}
if (sidebar) {
    sidebar.querySelectorAll('.item-menu a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) fecharMenu()
        })
    })
}
