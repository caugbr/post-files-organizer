window.addEventListener('DOMContentLoaded', () => {
    const dirLinks = $list('.downloads a.dir');
    if (dirLinks.length) {
        Array.from(dirLinks).forEach(a => {
            a.removeChild($single('.remove-dir', a));
            $single('.folder-items', a.closest('li')).classList.add('visible');
            a.addEventListener('click', event => {
                event.preventDefault();
                const subUl = $single('ul', event.target.closest('li'));
                subUl.classList.toggle('visible');
            });
        });
    }
});