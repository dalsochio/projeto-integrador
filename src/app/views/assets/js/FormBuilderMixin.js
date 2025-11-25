export default function formBuilderMixin() {
    return {
        items: [],
        fieldCounter: 0,
        rowCounter: 0,

        selectedItemId: null,
        selectedItemType: null,
        jsonOutput: '',
        draggedType: null,
        draggedFieldId: null,
        draggedFromCanvas: false,

        canvasSortConfig: null,
        columnSortConfig: null,
        fieldSortConfig: null,

        availableModules: [],
        moduleColumns: {},

        fieldTypes: [
            {
                name: 'VARCHAR',
                label: 'Text Field',
                preview: '<label class="input input-sm input-bordered flex items-center gap-2 opacity-70"><input type="text" class="grow" placeholder="Exemplo..." disabled /></label>'
            },
            {
                name: 'TEXT',
                label: 'Text Area',
                preview: '<textarea class="textarea textarea-sm textarea-bordered opacity-70 w-full" rows="2" placeholder="Texto longo..." disabled></textarea>'
            },
            {
                name: 'INT',
                label: 'Number',
                preview: '<input type="number" class="input input-sm input-bordered opacity-70 w-full" placeholder="123" disabled />'
            },
            {
                name: 'DATE',
                label: 'Date',
                preview: '<input type="date" class="input input-sm input-bordered opacity-70 w-full" disabled />'
            },
            {
                name: 'BOOLEAN',
                label: 'Toggle Simples',
                preview: '<label class="label cursor-pointer justify-start gap-2 opacity-70"><input type="checkbox" class="checkbox checkbox-sm" disabled /><span class="label-text text-xs">Sim/Não</span></label>'
            },
            {
                name: 'TOGGLE',
                label: 'Toggle Switch',
                preview: '<label class="label cursor-pointer justify-start gap-2 opacity-70"><input type="checkbox" class="toggle toggle-sm" disabled /><span class="label-text text-xs">Ativar</span></label>'
            },
            {
                name: 'SELECT',
                label: 'Select (Opções Manuais)',
                preview: '<select class="select select-sm select-bordered w-full opacity-70" disabled><option>Opção 1</option><option>Opção 2</option></select>'
            },
            {
                name: 'RADIO',
                label: 'Radio (Opções Manuais)',
                preview: '<div class="flex flex-col gap-1 opacity-70"><label class="label cursor-pointer justify-start gap-2"><input type="radio" class="radio radio-sm" disabled /><span class="label-text text-xs">Opção 1</span></label><label class="label cursor-pointer justify-start gap-2"><input type="radio" class="radio radio-sm" disabled /><span class="label-text text-xs">Opção 2</span></label></div>'
            },
            {
                name: 'CHECKBOX',
                label: 'Checkbox (Opções Manuais)',
                preview: '<div class="flex flex-col gap-1 opacity-70"><label class="label cursor-pointer justify-start gap-2"><input type="checkbox" class="checkbox checkbox-sm" disabled /><span class="label-text text-xs">Opção 1</span></label><label class="label cursor-pointer justify-start gap-2"><input type="checkbox" class="checkbox checkbox-sm" disabled /><span class="label-text text-xs">Opção 2</span></label></div>'
            },
            {
                name: 'REFERENCE_SELECT',
                label: 'Select (Referência)',
                preview: '<select class="select select-sm select-bordered w-full opacity-70" disabled><option>Ref: Outro Módulo...</option></select>'
            },
            {
                name: 'REFERENCE_RADIO',
                label: 'Radio (Referência)',
                preview: '<div class="flex flex-col gap-1 opacity-70"><label class="label cursor-pointer justify-start gap-2"><input type="radio" class="radio radio-sm" disabled /><span class="label-text text-xs">Ref: Opção 1</span></label><label class="label cursor-pointer justify-start gap-2"><input type="radio" class="radio radio-sm" disabled /><span class="label-text text-xs">Ref: Opção 2</span></label></div>'
            },
            {
                name: 'DECIMAL',
                label: 'Decimal',
                preview: '<input type="number" step="0.01" class="input input-sm input-bordered opacity-70 w-full" placeholder="0.00" disabled />'
            },
            {
                name: 'FILE',
                label: 'File Upload',
                preview: '<input type="file" class="file-input file-input-sm file-input-bordered w-full opacity-70" disabled />'
            },
            {
                name: 'COLOR',
                label: 'Color Picker',
                preview: '<div class="flex gap-2 opacity-70"><input type="color" class="w-10 h-8 rounded border-2" disabled /><input type="text" class="input input-sm input-bordered flex-1" placeholder="#000000" disabled /></div>'
            },
            {
                name: 'WYSIWYG',
                label: 'Rich Text (WYSIWYG)',
                preview: '<div class="border border-base-300 rounded p-2 text-xs opacity-70 bg-base-50">Rich Text Editor</div>'
            },
            {
                name: 'MARKDOWN',
                label: 'Markdown Editor',
                preview: '<div class="border border-base-300 rounded p-2 text-xs opacity-70 bg-base-50 font-mono"># Markdown</div>'
            },
            {
                name: 'CODE',
                label: 'Code Editor',
                preview: '<div class="border border-base-300 rounded p-2 text-xs opacity-70 bg-base-900 text-green-400 font-mono">{ code }</div>'
            }
        ],

        initFormBuilder() {
            const self = this;

            this.canvasSortConfig = {
                animation: 150,
                handle: '.drag-handle',
                group: {name: 'canvas-group', pull: false, put: false},
                swapThreshold: 0.65,
                onStart(evt) {
                    const itemId = evt.item.getAttribute('data-item-id');
                    if (itemId) {
                        self.draggedFieldId = itemId;
                        self.draggedFromCanvas = true;
                    }
                }
            };

            this.columnSortConfig = {
                animation: 150,
                handle: '.column-drag-handle'
            };

            this.fieldSortConfig = {
                animation: 150,
                handle: '.field-drag-handle',
                group: {name: 'fields-group', pull: false, put: false},
                swapThreshold: 0.65,
                onStart(evt) {
                    const itemId = evt.item.getAttribute('data-item-id');
                    if (itemId) {
                        self.draggedFieldId = itemId;
                        self.draggedFromCanvas = false;
                    }
                }
            };
        },

        clearAll() {
            showConfirmModal({
                title: 'Limpar Todos os Campos',
                message: 'Tem certeza que deseja limpar todos os campos?',
                confirmText: 'Sim, Limpar Tudo',
                cancelText: 'Cancelar',
                type: 'warning',
                onConfirm: () => {
                    this.items = [];
                    this.selectedItemId = null;
                    this.selectedItemType = null;
                }
            });
        },

        handleFieldDragStart(event, fieldId, fromCanvas) {
            this.draggedFieldId = fieldId;
            this.draggedFromCanvas = fromCanvas;
            this.draggedType = null;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', fieldId);
        },

        handlePaletteDragStart(event, type) {
            this.draggedType = type;
            this.draggedFieldId = null;
            this.draggedFromCanvas = false;
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/plain', type);
        },

        handleCanvasDrop(event) {
            event.preventDefault();

            if (this.draggedType) {
                const dropTarget = event.target.closest('[x-sort]');
                if (dropTarget) {
                    const children = Array.from(dropTarget.children).filter(el => el.hasAttribute('data-item-id'));
                    const mouseY = event.clientY;
                    let insertIndex = this.items.length;

                    for (let i = 0; i < children.length; i++) {
                        const rect = children[i].getBoundingClientRect();
                        const midpoint = rect.top + rect.height / 2;
                        if (mouseY < midpoint) {
                            insertIndex = i;
                            break;
                        }
                    }

                    const newItem = this.draggedType === 'ROW' ? this.createRow() : this.createField(this.draggedType);
                    this.items.splice(insertIndex, 0, newItem);
                } else {
                    this.addItem(this.draggedType);
                }
                this.draggedType = null;
            } else if (this.draggedFieldId && !this.draggedFromCanvas) {
                let movedField = null;
                for (const item of this.items) {
                    if (item.itemType === 'row' && item.columns) {
                        for (const col of item.columns) {
                            const fieldIndex = col.fields?.findIndex(f => f.id === this.draggedFieldId);
                            if (fieldIndex !== undefined && fieldIndex >= 0) {
                                movedField = col.fields.splice(fieldIndex, 1)[0];
                                break;
                            }
                        }
                        if (movedField) break;
                    }
                }

                if (movedField) {
                    const dropTarget = event.target.closest('[x-sort]');
                    let insertIndex = this.items.length;

                    if (dropTarget) {
                        const children = Array.from(dropTarget.children).filter(el => el.hasAttribute('data-item-id'));
                        const mouseY = event.clientY;

                        for (let i = 0; i < children.length; i++) {
                            const rect = children[i].getBoundingClientRect();
                            const midpoint = rect.top + rect.height / 2;
                            if (mouseY < midpoint) {
                                insertIndex = i;
                                break;
                            }
                        }
                    }

                    this.items.splice(insertIndex, 0, movedField);
                }

                this.draggedFieldId = null;
                this.draggedFromCanvas = false;
            }
        },

        handleColumnDrop(event, rowId, columnId) {
            event.preventDefault();
            event.stopPropagation();

            if (this.draggedType) {
                if (this.draggedType === 'ROW') {
                    flash('Não é possível adicionar Layout Row dentro de colunas!', 'warn');
                    this.draggedType = null;
                    return;
                }
                this.addFieldToColumnDirect(rowId, columnId, this.draggedType);
                this.draggedType = null;
            } else if (this.draggedFieldId && this.draggedFromCanvas) {
                const fieldIndex = this.items.findIndex(i => i.id === this.draggedFieldId);
                if (fieldIndex >= 0) {
                    const field = this.items[fieldIndex];

                    if (field.itemType === 'row') {
                        flash('Não é possível adicionar Layout Row dentro de colunas!', 'warn');
                        this.draggedFieldId = null;
                        this.draggedFromCanvas = false;
                        return;
                    }

                    this.items.splice(fieldIndex, 1);

                    const row = this.items.find(i => i.id === rowId);
                    if (row && row.columns) {
                        const col = row.columns.find(c => c.id === columnId);
                        if (col && col.fields) {
                            col.fields.push(field);
                        }
                    }
                }

                this.draggedFieldId = null;
                this.draggedFromCanvas = false;
            }
        },

        addItem(type) {
            if (type === 'ROW') {
                this.items.push(this.createRow());
            } else {
                this.items.push(this.createField(type));
            }
        },

        addFieldToColumnDirect(rowId, columnId, type) {
            const row = this.items.find(i => i.id === rowId);
            if (row && row.columns) {
                const col = row.columns.find(c => c.id === columnId);
                if (col && col.fields) {
                    col.fields.push(this.createField(type));
                }
            }
        },

        createField(type) {
            const inputTypeMap = {
                'VARCHAR': 'text', 'TEXT': 'textarea', 'INT': 'number', 'DATE': 'date',
                'DATETIME': 'datetime-local', 'BOOLEAN': 'checkbox', 'TOGGLE': 'toggle',
                'SELECT': 'select', 'RADIO': 'radio', 'CHECKBOX': 'checkbox', 'ENUM': 'select',
                'DECIMAL': 'number', 'JSON': 'textarea', 'TINYINT': 'number', 'FILE': 'file',
                'COLOR': 'color', 'WYSIWYG': 'wysiwyg', 'MARKDOWN': 'markdown', 'CODE': 'code',
                'REFERENCE_SELECT': 'select', 'REFERENCE_RADIO': 'radio'
            };

            const sqlTypeMap = {
                'FILE': 'VARCHAR',
                'COLOR': 'VARCHAR',
                'TOGGLE': 'TINYINT',
                'SELECT': 'VARCHAR',
                'RADIO': 'VARCHAR',
                'WYSIWYG': 'TEXT',
                'MARKDOWN': 'TEXT',
                'CODE': 'TEXT',
                'REFERENCE_SELECT': 'INT',
                'REFERENCE_RADIO': 'INT'
            };

            const actualType = sqlTypeMap[type] || type;
            const actualInputType = inputTypeMap[type] || 'text';

            let defaultLength = null;
            if (['VARCHAR', 'FILE', 'COLOR', 'RADIO'].includes(type)) {
                defaultLength = '255';
            } else if (['REFERENCE_SELECT', 'REFERENCE_RADIO', 'INT'].includes(type)) {
                defaultLength = '11';
            }

            const field = {
                id: `field_${++this.fieldCounter}`,
                itemType: 'field',
                display_name: `Campo ${this.fieldCounter}`,
                type: type,
                length: defaultLength,
                is_nullable: false,
                default_value: null,
                is_unique: false,
                is_primary: false,
                auto_increment: false,
                is_visible_list: true,
                is_visible_form: true,
                is_editable: true,
                is_searchable: false,
                input_type: actualInputType,
                input_placeholder: null,
                input_prefix: null,
                input_suffix: null,
                help_text: null,
                column_width: 12
            };

            if (['REFERENCE_SELECT', 'REFERENCE_RADIO'].includes(type)) {
                field.foreign_table = null;
                field.foreign_column = null;
                field.is_foreign_key = true;
            }

            if (['SELECT', 'RADIO', 'CHECKBOX'].includes(type)) {
                field.manual_options = [];
                field.use_separate_value = false;
            }

            return field;
        },

        createRow() {
            const numCols = 2;
            const columns = [];

            for (let i = 0; i < numCols; i++) {
                columns.push({
                    id: `col_${++this.rowCounter}_${i}`,
                    width: Math.floor(12 / numCols),
                    fields: []
                });
            }

            return {
                id: `row_${++this.rowCounter}`,
                itemType: 'row',
                columns: columns
            };
        },

        moveItemUp(index) {
            if (index > 0) {
                [this.items[index], this.items[index - 1]] = [this.items[index - 1], this.items[index]];
            }
        },

        moveItemDown(index) {
            if (index < this.items.length - 1) {
                [this.items[index], this.items[index + 1]] = [this.items[index + 1], this.items[index]];
            }
        },

        moveFieldInColumn(rowId, colId, fieldIndex, direction) {
            const col = this.findColumn(rowId, colId);
            if (!col || !col.fields) return;

            if (direction === 'up' && fieldIndex > 0) {
                [col.fields[fieldIndex], col.fields[fieldIndex - 1]] = [col.fields[fieldIndex - 1], col.fields[fieldIndex]];
            } else if (direction === 'down' && fieldIndex < col.fields.length - 1) {
                [col.fields[fieldIndex], col.fields[fieldIndex + 1]] = [col.fields[fieldIndex + 1], col.fields[fieldIndex]];
            }
        },

        deleteItem(index) {
            showConfirmModal({
                title: 'Excluir Item',
                message: 'Tem certeza que deseja excluir este item?',
                confirmText: 'Sim, Excluir',
                cancelText: 'Cancelar',
                type: 'warning',
                onConfirm: () => {
                    const item = this.items[index];
                    if (this.selectedItemId === item.id) {
                        this.selectedItemId = null;
                        this.selectedItemType = null;
                    }
                    this.items.splice(index, 1);
                }
            });
        },

        deleteFieldFromColumn(rowId, colId, fieldIndex) {
            showConfirmModal({
                title: 'Excluir Campo',
                message: 'Tem certeza que deseja excluir este campo?',
                confirmText: 'Sim, Excluir',
                cancelText: 'Cancelar',
                type: 'warning',
                onConfirm: () => {
                    const col = this.findColumn(rowId, colId);
                    if (col && col.fields) {
                        const field = col.fields[fieldIndex];
                        if (field && this.selectedItemId === field.id) {
                            this.selectedItemId = null;
                            this.selectedItemType = null;
                        }
                        col.fields.splice(fieldIndex, 1);
                    }
                }
            });
        },

        selectItem(itemId, itemType) {
            this.selectedItemId = itemId;
            this.selectedItemType = itemType;
        },

        findField(fieldId) {
            let field = this.items.find(i => i.itemType === 'field' && i.id === fieldId);
            if (field) return field;

            for (const item of this.items) {
                if (item.itemType === 'row' && item.columns) {
                    for (const col of item.columns) {
                        field = col.fields?.find(f => f.id === fieldId);
                        if (field) return field;
                    }
                }
            }
            return null;
        },

        findColumn(rowId, colId) {
            const row = this.items.find(i => i.id === rowId);
            if (row && row.columns) {
                return row.columns.find(c => c.id === colId);
            }
            return null;
        },

        getFieldPreviewHtml(field) {
            const prefix = field.input_prefix ? `<span class="text-base-content/70">${field.input_prefix}</span>` : '';
            const suffix = field.input_suffix ? `<span class="text-base-content/70">${field.input_suffix}</span>` : '';
            const helpText = field.help_text ? `<label class="label"><span class="label-text-alt">${field.help_text}</span></label>` : '';
            const placeholder = field.input_placeholder || '';

            const templates = {
                'VARCHAR': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><label class="input input-bordered flex items-center gap-2 opacity-70">${prefix}<input type="text" class="grow" placeholder="${placeholder}" disabled />${suffix}</label>${helpText}</div>`,
                'TEXT': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><textarea class="textarea textarea-bordered opacity-70" rows="3" placeholder="${placeholder}" disabled></textarea>${helpText}</div>`,
                'INT': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><input type="number" class="input input-bordered opacity-70 w-full" placeholder="${placeholder || '0'}" disabled />${helpText}</div>`,
                'TINYINT': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><input type="number" class="input input-bordered opacity-70 w-full" placeholder="${placeholder || '0'}" disabled />${helpText}</div>`,
                'DECIMAL': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><input type="number" step="0.01" class="input input-bordered opacity-70 w-full" placeholder="${placeholder || '0.00'}" disabled />${helpText}</div>`,
                'DATE': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><input type="date" class="input input-bordered opacity-70 w-full" disabled />${helpText}</div>`,
                'DATETIME': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><input type="datetime-local" class="input input-bordered opacity-70 w-full" disabled />${helpText}</div>`,
                'BOOLEAN': `<div class="form-control"><label class="label cursor-pointer justify-start gap-4"><input type="checkbox" class="checkbox opacity-70" disabled /><span class="label-text font-medium">${field.display_name}</span></label>${helpText}</div>`,
                'TOGGLE': `<div class="form-control"><label class="label cursor-pointer justify-start gap-4"><input type="checkbox" class="toggle opacity-70" disabled /><span class="label-text font-medium">${field.display_name}</span></label>${helpText}</div>`,
                'SELECT': (() => {
                    const opts = field.manual_options || [];
                    const optionsHtml = opts.map(opt => '<option>' + (opt.label || 'Opção') + '</option>').join('');
                    return `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><select class="select select-bordered w-full opacity-70" disabled><option>${placeholder || 'Selecione...'}</option>${optionsHtml}</select>${helpText}</div>`;
                })(),
                'RADIO': (() => {
                    const opts = (field.manual_options && field.manual_options.length > 0) ? field.manual_options : [{label: 'Opção 1'}, {label: 'Opção 2'}];
                    const optionsHtml = opts.map((opt, i) => '<label class="label cursor-pointer justify-start gap-3"><input type="radio" name="radio_' + field.name + '" class="radio" disabled /><span class="label-text">' + (opt.label || 'Opção ' + (i + 1)) + '</span></label>').join('');
                    return `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><div class="flex flex-col gap-2 opacity-70">${optionsHtml}</div>${helpText}</div>`;
                })(),
                'CHECKBOX': (() => {
                    const opts = (field.manual_options && field.manual_options.length > 0) ? field.manual_options : [{label: 'Opção 1'}, {label: 'Opção 2'}];
                    const optionsHtml = opts.map((opt, i) => '<label class="label cursor-pointer justify-start gap-3"><input type="checkbox" class="checkbox" disabled /><span class="label-text">' + (opt.label || 'Opção ' + (i + 1)) + '</span></label>').join('');
                    return `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><div class="flex flex-col gap-2 opacity-70">${optionsHtml}</div>${helpText}</div>`;
                })(),
                'REFERENCE_SELECT': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><select class="select select-bordered w-full opacity-70" disabled><option>${field.foreign_table ? 'Ref: ' + field.foreign_table : placeholder || 'Selecione...'}</option></select>${helpText}</div>`,
                'REFERENCE_RADIO': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><div class="flex flex-col gap-2 opacity-70"><label class="label cursor-pointer justify-start gap-3"><input type="radio" name="radio_${field.name}" class="radio" disabled /><span class="label-text">${field.foreign_table ? 'Ref: Opção 1' : 'Opção 1'}</span></label><label class="label cursor-pointer justify-start gap-3"><input type="radio" name="radio_${field.name}" class="radio" disabled /><span class="label-text">${field.foreign_table ? 'Ref: Opção 2' : 'Opção 2'}</span></label></div>${helpText}</div>`,
                'ENUM': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><select class="select select-bordered w-full opacity-70" disabled><option>${placeholder || 'Selecione...'}</option></select>${helpText}</div>`,
                'JSON': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><textarea class="textarea textarea-bordered font-mono opacity-70" rows="3" placeholder='${placeholder || '{"key": "value"}'}' disabled></textarea>${helpText}</div>`,
                'FILE': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><input type="file" class="file-input file-input-bordered w-full opacity-70" disabled />${helpText}</div>`,
                'COLOR': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><div class="flex items-center gap-3 opacity-70"><input type="color" class="w-16 h-12 rounded border-2 border-base-300" disabled /><input type="text" placeholder="${placeholder || '#000000'}" class="input input-bordered flex-1" disabled /></div>${helpText}</div>`,
                'WYSIWYG': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><div class="border-2 border-base-300 rounded-lg p-4 bg-base-50 opacity-70 min-h-[120px] flex items-center justify-center"><span class="text-base-content/50">Rich Text Editor (WYSIWYG)</span></div>${helpText}</div>`,
                'MARKDOWN': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><div class="border-2 border-base-300 rounded-lg p-4 bg-base-50 opacity-70 min-h-[120px] font-mono flex items-center justify-center"><span class="text-base-content/50"># Markdown Editor</span></div>${helpText}</div>`,
                'CODE': `<div class="form-control w-full"><label class="label"><span class="label-text font-medium">${field.display_name}</span></label><div class="border-2 border-base-300 rounded-lg p-4 bg-base-900 opacity-70 min-h-[120px] font-mono flex items-center justify-center"><span class="text-green-400">{ code editor }</span></div>${helpText}</div>`
            };

            return templates[field.type] || templates['VARCHAR'];
        },

        changeColumns(rowId, newCount) {
            const row = this.items.find(i => i.id === rowId);
            if (!row) return;

            const current = row.columns.length;

            if (newCount > current) {
                for (let i = current; i < newCount; i++) {
                    row.columns.push({
                        id: `col_${rowId}_${i}`,
                        width: Math.floor(12 / newCount),
                        fields: []
                    });
                }
            } else if (newCount < current) {
                const removed = row.columns.splice(newCount);
                removed.forEach(col => {
                    if (col.fields && row.columns[row.columns.length - 1].fields) {
                        row.columns[row.columns.length - 1].fields.push(...col.fields);
                    }
                });
            }

            row.columns.forEach(col => {
                col.width = Math.floor(12 / newCount);
            });
        },

        generateFieldsArray() {
            const sqlTypeMap = {
                'FILE': 'VARCHAR',
                'COLOR': 'VARCHAR',
                'TOGGLE': 'TINYINT',
                'SELECT': 'VARCHAR',
                'RADIO': 'VARCHAR',
                'WYSIWYG': 'TEXT',
                'MARKDOWN': 'TEXT',
                'CODE': 'TEXT',
                'REFERENCE_SELECT': 'INT',
                'REFERENCE_RADIO': 'INT'
            };

            const output = [];

            this.items.forEach(item => {
                if (item.itemType === 'field') {
                    const { id, itemType, ...fieldData } = item;

                    if (fieldData.manual_options && Array.isArray(fieldData.manual_options)) {
                        fieldData.manual_options = JSON.parse(JSON.stringify(fieldData.manual_options));
                    }

                    const originalType = fieldData.type;
                    if (sqlTypeMap[fieldData.type]) {
                        fieldData.type = sqlTypeMap[fieldData.type];
                    }
                    
                    if ((originalType === 'REFERENCE_SELECT' || originalType === 'REFERENCE_RADIO') && fieldData.foreign_table) {
                        fieldData.is_foreign_key = true;
                    }

                    // Converter booleanos para inteiros
                    fieldData.is_nullable = fieldData.is_nullable ? 1 : 0;
                    fieldData.is_unique = fieldData.is_unique ? 1 : 0;
                    fieldData.is_primary = fieldData.is_primary ? 1 : 0;
                    fieldData.auto_increment = fieldData.auto_increment ? 1 : 0;
                    fieldData.is_visible_list = fieldData.is_visible_list ? 1 : 0;
                    fieldData.is_visible_form = fieldData.is_visible_form ? 1 : 0;
                    fieldData.is_editable = fieldData.is_editable ? 1 : 0;
                    fieldData.is_searchable = fieldData.is_searchable ? 1 : 0;

                    output.push(fieldData);
                } else if (item.itemType === 'row' && item.columns) {
                    item.columns.forEach(col => {
                        if (col.fields) {
                            col.fields.forEach(field => {
                                const {id, itemType, ...fieldData} = field;

                                if (fieldData.manual_options && Array.isArray(fieldData.manual_options)) {
                                    fieldData.manual_options = JSON.parse(JSON.stringify(fieldData.manual_options));
                                }

                                const originalType = fieldData.type;
                                if (sqlTypeMap[fieldData.type]) {
                                    fieldData.type = sqlTypeMap[fieldData.type];
                                }
                                
                                if ((originalType === 'REFERENCE_SELECT' || originalType === 'REFERENCE_RADIO') && fieldData.foreign_table) {
                                    fieldData.is_foreign_key = true;
                                }

                                // Converter booleanos para inteiros
                                fieldData.is_nullable = fieldData.is_nullable ? 1 : 0;
                                fieldData.is_unique = fieldData.is_unique ? 1 : 0;
                                fieldData.is_primary = fieldData.is_primary ? 1 : 0;
                                fieldData.auto_increment = fieldData.auto_increment ? 1 : 0;
                                fieldData.is_visible_list = fieldData.is_visible_list ? 1 : 0;
                                fieldData.is_visible_form = fieldData.is_visible_form ? 1 : 0;
                                fieldData.is_editable = fieldData.is_editable ? 1 : 0;
                                fieldData.is_searchable = fieldData.is_searchable ? 1 : 0;

                                fieldData.column_width = col.width;
                                output.push(fieldData);
                            });
                        }
                    });
                }
            });

            return output;
        },

        getTotalFieldsCount() {
            let count = 0;
            this.items.forEach(item => {
                if (item.itemType === 'field') {
                    count++;
                } else if (item.itemType === 'row' && item.columns) {
                    item.columns.forEach(col => {
                        count += (col.fields?.length || 0);
                    });
                }
            });
            return count;
        },

        addOption(fieldId) {
            const field = this.findField(fieldId);
            if (!field) return;

            if (!field.manual_options) {
                field.manual_options = [];
            }

            field.manual_options.push({
                label: '',
                value: ''
            });
        },

        removeOption(fieldId, index) {
            const field = this.findField(fieldId);
            if (!field || !field.manual_options) return;

            field.manual_options.splice(index, 1);
        },

        async loadModuleColumns(field) {
            if (!field || !field.foreign_table) {
                return;
            }

            if (this.moduleColumns[field.foreign_table]) {
                return;
            }

            const url = `/panel/module/columns/${field.foreign_table}`;

            try {
                const response = await fetch(url);

                if (response.ok) {
                    const data = await response.json();

                    this.moduleColumns = {
                        ...this.moduleColumns,
                        [field.foreign_table]: data.columns || []
                    };
                }
            } catch (error) {
                this.moduleColumns = {
                    ...this.moduleColumns,
                    [field.foreign_table]: []
                };
            }
        }
    };
}
