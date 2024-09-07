window.addEventListener('DOMContentLoaded', () => {
    const dirButton = $single('button.new-directory');
    if (dirButton) {
        dirButton.addEventListener('click', () => {
            const dlg = new Dialog(pfoStrings.createDir);
            dlg.pop.on('open', () => {
                const comboOri = $single('select[name="directories"]');
                if (combo) {
                    const combo = comboOri.cloneNode(true);
                    combo.options[0].innerText = pfoStrings.none;
                    combo.addEventListener('input', event => {
                        const input = $single('input[name="new_directory_parent"]');
                        input.value = event.target.value;
                    });
                    const label = tag('label', { style: 'display: inline;' }, pfoStrings.subDirOf);
                    const subdir = tag('div', { style: 'margin-top: 0.5rem;' }, [label, combo]);
                    $single('.content-prompt').appendChild(subdir);
                }
            });
            dlg.prompt(pfoStrings.saveMsg, pfoStrings.saveTitle).then(dirname => {
                if (dirname) {
                    const input = $single('input[name="new_directory"]');
                    input.value = dirname;
                    input.form.submit();
                }
            });
        });
        const dirLinks = $list('.downloads a.dir');
        Array.from(dirLinks).forEach(a => {
            a.addEventListener('click', event => {
                event.preventDefault();
                const subUl = $single('ul', event.target.closest('li'));
                subUl.classList.toggle('visible');
            });
        });
        const removeLinks = $list('.downloads .remove-dir');
        Array.from(removeLinks).forEach(a => {
            a.addEventListener('click', event => {
                event.stopPropagation();
                const dirname = event.target.closest('a.dir').getAttribute('data-id');
                console.log('path', dirname);
                const dlg = new Dialog('Remover diretório');
                const ask = pfoStrings.removeMsg.replace('%s', `<strong>${dirname}</strong>`);
                dlg.confirm(ask).then(remove => {
                    if (remove) {
                        const input = $single('input[name="remove_directory"]');
                        input.value = dirname;
                        input.form.submit();
                    }
                });
            });
        });
        const combo = $single('select[name="directories"]');
        if (combo) {
            combo.addEventListener('input', event => {
                $single('input[name="move_to_directory"]').value = event.target.value;
                if (event.target.value) {
                    $single('.move-files').disabled = false;
                    selectableFiles(true);
                } else {
                    $single('.move-files').disabled = true;
                    selectableFiles(false);
                }
            });
        }
        const moveButton = $single('button.move-files');
        if (moveButton) {
            moveButton.addEventListener('click', event => {
                event.target.form.submit();
            });
        }
    }
});

function selectableFiles(show) {
    const fileLinks = $list('.downloads a:not(.dir)');
    Array.from(fileLinks).forEach(a => {
        if (show) {
            const check = tag('input', { 
                name: 'files_to_move[]', 
                type: 'checkbox', 
                value: a.getAttribute('data-id'), 
                class: 'move-file'
            });
            a.appendChild(check);
        } else {
            const inp = a.querySelector('input');
            a.removeChild(inp);
        }
    });
}