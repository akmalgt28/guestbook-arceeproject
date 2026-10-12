import { ArrowLeft, ArrowUpDown, ArrowUpRight, ArrowUpToLine, BookOpen, CalendarDays, ChartNoAxesColumn, Check, ChevronDown, ChevronLeft, ChevronRight, ChevronUp, Circle, CircleAlert, CircleCheck, CircleDot, CircleX, ClipboardCheck, Clock, Copy, createIcons, Eye, FileText, Grid2X2, GripVertical, Heart, House, Image, Images, Info, Layers3, Link, LogOut, Mail, Maximize2, Menu, MessageCircle, MessageSquareText, Minus, Monitor, MousePointer2, Music2, Phone, Palette, Pencil, Plus, QrCode, RotateCw, Save, ScanLine, Send, Settings, SlidersHorizontal, Sparkles, Square, Star, Trash2, Triangle, Upload, UserRound, Users, MapPin, X } from 'lucide';

import { Html5Qrcode } from 'html5-qrcode';

window.Html5Qrcode = Html5Qrcode;

window.guestbookDatePicker = (value) => ({
    open: false,
    value,
    cursor: typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(value + 'T00:00:00') : new Date(),
    get monthLabel() {
        return this.cursor.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    },
    get days() {
        const year = this.cursor.getFullYear();
        const month = this.cursor.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const totalDays = new Date(year, month + 1, 0).getDate();
        const cells = Array.from({ length: firstDay }, () => null);
        for (let day = 1; day <= totalDays; day += 1) {
            const date = new Date(year, month, day);
            cells.push({ day, value: this.format(date) });
        }
        return cells;
    },
    format(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    },
    select(date) {
        this.value = date;
        this.open = false;
    },
    previous() {
        this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() - 1, 1);
    },
    next() {
        this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + 1, 1);
    },
});

window.guestbookTimePicker = (value) => ({
    open: false,
    value,
    hours: Array.from({ length: 24 }, (_, hour) => String(hour).padStart(2, '0')),
    minutes: ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'],
    get hour() {
        return this.value?.split(':')[0] ?? '';
    },
    get minute() {
        return this.value?.split(':')[1] ?? '';
    },
    choose(hour, minute) {
        this.value = `${hour}:${minute}`;
        this.open = false;
    },
});

window.templateCanvas = (entangledElements) => ({
    elements: entangledElements,
    selectedId: null,
    toolMenu: false,
    dragState: null,
    init() {
        this.elements = Array.isArray(this.elements) ? this.elements : [];
    },
    get selected() {
        return this.elements.find((element) => element.id === this.selectedId) ?? null;
    },
    add(type) {
        const defaults = {
            rectangle: { width: 28, height: 16, fill: '#168DE2', radius: 12 },
            line: { width: 30, height: 1, fill: '#0B4AA2', radius: 0 },
            arrow: { width: 30, height: 2, fill: '#0B4AA2', radius: 0 },
            ellipse: { width: 20, height: 20, fill: '#168DE2', radius: 999 },
            polygon: { width: 24, height: 22, fill: '#8BC4F0', radius: 0 },
            star: { width: 22, height: 22, fill: '#0B4AA2', radius: 0 },
            image: { width: 24, height: 18, fill: '#DCEEFF', radius: 14, url: '' },
        };
        const id = window.crypto?.randomUUID?.() ?? `element-${Date.now()}`;
        const element = { id, type, x: 38, y: 32, rotation: 0, opacity: 100, ...defaults[type] };
        this.elements.push(element);
        this.selectedId = id;
        this.toolMenu = false;
    },
    select(id) {
        this.selectedId = id;
        const element = this.selected;
        if (element?.type === 'ellipse') {
            element.height = element.width;
        }
    },
    syncCircleSize(field) {
        if (this.selected?.type !== 'ellipse') {
            return;
        }
        const size = Math.max(1, Number(this.selected[field]) || 1);
        this.selected.width = size;
        this.selected.height = size;
    },
    startDrag(event, id) {
        if (event.button !== undefined && event.button !== 0) return;
        this.select(id);
        const canvas = event.currentTarget.closest('[data-template-canvas]');
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        const element = this.elements.find((item) => item.id === id);
        if (!element) return;
        this.dragState = { id, rect, offsetX: (event.clientX - rect.left) / rect.width * 100 - Number(element.x), offsetY: (event.clientY - rect.top) / rect.height * 100 - Number(element.y) };
        event.currentTarget.setPointerCapture?.(event.pointerId);
    },
    drag(event) {
        if (!this.dragState) return;
        const element = this.elements.find((item) => item.id === this.dragState.id);
        if (!element) return;
        const { rect, offsetX, offsetY } = this.dragState;
        element.x = Math.max(-30, Math.min(100 - Number(element.width), (event.clientX - rect.left) / rect.width * 100 - offsetX));
        element.y = Math.max(-30, Math.min(100 - Number(element.height), (event.clientY - rect.top) / rect.height * 100 - offsetY));
    },
    stopDrag() {
        this.dragState = null;
    },
    deleteSelected() {
        if (!this.selectedId) return;
        this.elements = this.elements.filter((element) => element.id !== this.selectedId);
        this.selectedId = null;
    },
    duplicateSelected() {
        if (!this.selected) return;
        const copy = { ...this.selected, id: window.crypto?.randomUUID?.() ?? `element-${Date.now()}`, x: Math.min(100 - Number(this.selected.width), Number(this.selected.x) + 4), y: Math.min(100 - Number(this.selected.height), Number(this.selected.y) + 4) };
        this.elements.push(copy);
        this.selectedId = copy.id;
    },
    elementStyle(element) {
        const style = `left:${element.x}%;top:${element.y}%;width:${element.width}%;height:${element.height}%;transform:rotate(${element.rotation}deg);opacity:${element.opacity / 100};background:${element.fill};border-radius:${element.radius}px;`;
        return style;
    },
    shapeClass(element) {
        return { 'template-shape--line': element.type === 'line', 'template-shape--arrow': element.type === 'arrow', 'template-shape--ellipse': element.type === 'ellipse', 'template-shape--polygon': element.type === 'polygon', 'template-shape--star': element.type === 'star' };
    },
});

function initCustomSelects() {
    document.querySelectorAll('select:not([data-customized])').forEach((select) => {
        select.dataset.customized = 'true';
        const wrapper = document.createElement('div');
        wrapper.className = 'guestbook-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.classList.add('guestbook-select__native');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'guestbook-select__trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        const icon = document.createElement('span');
        icon.className = 'guestbook-select__icon';
        icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
        trigger.append(icon);

        const panel = document.createElement('div');
        panel.className = 'guestbook-select__panel';
        panel.setAttribute('role', 'listbox');

        const close = () => {
            panel.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        };
        const sync = () => {
            const selected = select.options[select.selectedIndex];
            while (trigger.firstChild && trigger.firstChild !== icon) {
                trigger.firstChild.remove();
            }
            if (!trigger.contains(icon)) {
                trigger.appendChild(icon);
            }
            trigger.insertBefore(document.createTextNode(selected?.textContent ?? ''), icon);
            panel.replaceChildren();
            Array.from(select.options).forEach((option) => {
                if (option.disabled) {
                    return;
                }
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'guestbook-select__option';
                item.textContent = option.textContent;
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
                item.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                    sync();
                    close();
                });
                panel.appendChild(item);
            });
        };

        trigger.addEventListener('click', () => {
            const isOpen = panel.classList.toggle('is-open');
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) close();
        });
        select.addEventListener('change', sync);
        new MutationObserver(sync).observe(select, { childList: true, subtree: true, attributes: true });
        wrapper.append(trigger, panel);
        sync();
    });
}

document.addEventListener('DOMContentLoaded', initCustomSelects);
document.addEventListener('livewire:navigated', initCustomSelects);
if (window.Livewire) {
    document.addEventListener('livewire:init', () => Livewire.hook('morph.updated', initCustomSelects));
}
const lucideIcons = {
    ArrowLeft,
    ArrowUpDown,
    ArrowUpRight,
    ArrowUpToLine,
    BookOpen,
    CalendarDays,
    ChartNoAxesColumn,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    CircleAlert,
    CircleCheck,
    CircleDot,
    CircleX,
    ClipboardCheck,
    Clock,
    Copy,
    Eye,
    FileText,
    Grid2X2,
    GripVertical,
    Heart,
    House,
    Image,
    Images,
    Info,
    Layers3,
    Link,
    LogOut,
    Mail,
    Maximize2,
    Menu,
    MessageCircle,
    MessageSquareText,
    Minus,
    Monitor,
    MousePointer2,
    Music2,
    Phone,
    Palette,
    Pencil,
    Plus,
    QrCode,
    RotateCw,
    Save,
    ScanLine,
    Send,
    Settings,
    SlidersHorizontal,
    Sparkles,
    Square,
    Star,
    Trash2,
    Triangle,
    Upload,
    UserRound,
    MapPin,
    Users,
    X,
};

function initLucideIcons() {
    createIcons({ icons: lucideIcons });
}

document.addEventListener('DOMContentLoaded', initLucideIcons);
document.addEventListener('livewire:navigated', initLucideIcons);
if (window.Livewire) {
    document.addEventListener('livewire:init', () => Livewire.hook('morph.updated', initLucideIcons));
}









