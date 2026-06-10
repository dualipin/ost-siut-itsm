export function localize(dateStr: string){
        if (!dateStr) return ''
        const p = String(dateStr).split('-')
        if (p.length !== 3) return dateStr
        return `${ p[2] }/${ p[1] }/${ p[0] }`
}
