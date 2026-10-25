// Kasia's photos used directly by the templates (Vite::asset('zdjecia/...')).
import.meta.glob('/zdjecia/*.webp', { eager: true, query: '?url', import: 'default' });
