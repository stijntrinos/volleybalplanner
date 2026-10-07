import interact from 'interactjs'

/*
 * Day planner (HomeMatches): drag and drop of match cards, the undo toast and
 * scrolling to the first card / a chosen search result. The panel runs in SPA
 * mode, so everything re-initialises on livewire:navigated.
 */

let initialized = false
let lastAutoScrolledView = null

function scrollToFirstPlannerItem(planner) {
    const view = planner.dataset.plannerView
    if (!view || view === lastAutoScrolledView) return

    const scroll = planner.querySelector('.planner-scroll')
    const cards = [...planner.querySelectorAll('.planner-fields .planner-card')]
    if (!scroll || cards.length === 0) return

    const firstTop = Math.min(...cards.map(card => Number.parseFloat(card.style.top)).filter(Number.isFinite))
    if (!Number.isFinite(firstTop)) return

    requestAnimationFrame(() => requestAnimationFrame(() => {
        const headingHeight = planner.querySelector('.planner-headings')?.offsetHeight ?? 68
        const contextAbove = 72
        scroll.scrollTop = Math.max(0, headingHeight + firstTop - contextAbove)
        lastAutoScrolledView = view
    }))
}

function componentFor(element) {
    const root = element?.closest('[wire\\:id]')
    return root ? window.Livewire.find(root.getAttribute('wire:id')) : null
}

/**
 * The timetable measurements live in PHP (App\Planner\Support\Timetable) and
 * arrive as data attributes. Keeping a separate copy here would silently shift
 * the cards whenever one side changed.
 */
function timetableFor(element) {
    const planner = element.closest('[data-planner]')

    return {
        start: Number(planner?.dataset.timetableStart ?? 480),
        minutes: Number(planner?.dataset.timetableMinutes ?? 930),
        scale: Number(planner?.dataset.timetableScale ?? 1.2),
        step: Number(planner?.dataset.timetableStep ?? 15),
    }
}

function placementFor(zone, match) {
    const timetable = timetableFor(zone)
    const zoneRect = zone.getBoundingClientRect()
    const matchRect = match.getBoundingClientRect()
    const rawMinutes = Math.max(0, Math.min(timetable.minutes, (matchRect.top - zoneRect.top) / timetable.scale))
    const minutes = Math.round(rawMinutes / timetable.step) * timetable.step + timetable.start

    return {
        venueId: Number(zone.dataset.venueId),
        courtId: Number(zone.dataset.courtId),
        time: `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`,
    }
}

function initializePlanner() {
    const planner = document.querySelector('[data-planner]')
    if (!planner) return

    scrollToFirstPlannerItem(planner)

    interact('.planner-match').unset()
    interact('.planner-dropzone').unset()
    interact('.planner-dropzone[data-court-id]').unset()

    interact('.planner-match:not(.is-readonly)').draggable({
        inertia: false,
        autoScroll: true,
        listeners: {
            start(event) {
                event.target.classList.add('is-dragging')
            },
            move(event) {
                const target = event.target
                const x = (parseFloat(target.dataset.dragX) || 0) + event.dx
                const y = (parseFloat(target.dataset.dragY) || 0) + event.dy
                target.style.transform = `translate(${x}px, ${y}px)`
                target.dataset.dragX = x
                target.dataset.dragY = y
            },
            end(event) {
                event.target.classList.remove('is-dragging')
                event.target.style.transform = ''
                event.target.dataset.dragX = 0
                event.target.dataset.dragY = 0
            },
        },
    })

    // Only court columns accept a match; the hall duty column is no court.
    interact('.planner-dropzone[data-court-id]').dropzone({
        accept: '.planner-match:not(.is-readonly)',
        // For cards spanning two courts the pointer decides the target column;
        // surface overlap would hit both underlying courts at once.
        overlap: 'pointer',
        ondragenter(event) { event.target.classList.add('is-drop-target') },
        ondragleave(event) { event.target.classList.remove('is-drop-target') },
        ondrop(event) {
            const zone = event.target
            zone.classList.remove('is-drop-target')
            const match = event.relatedTarget
            const placement = placementFor(zone, match)
            componentFor(zone)?.call('move', Number(match.dataset.matchId), placement.venueId, placement.courtId, placement.time, false)
        },
    })
}

function initializePlannerEvents() {
    if (initialized) return

    initialized = true
    window.addEventListener('planner-moved', event => {
        const planner = document.querySelector('[data-planner]')
        const toast = document.createElement('div')
        toast.className = 'planner-undo-toast'
        const label = document.createElement('span')
        label.textContent = planner?.dataset.plannerMovedLabel ?? 'Wedstrijd verplaatst'
        const button = document.createElement('button')
        button.type = 'button'
        button.textContent = planner?.dataset.plannerUndoLabel ?? 'Ongedaan maken'
        toast.append(label, button)
        button.addEventListener('click', () => {
            componentFor(planner)?.call('undo', event.detail.token)
            toast.remove()
        })
        document.body.appendChild(toast)
        setTimeout(() => toast.remove(), 15000)
    })
    window.addEventListener('planner-refreshed', () => setTimeout(initializePlanner))
    window.addEventListener('planner-search-result-selected', event => {
        setTimeout(() => {
            const planner = document.querySelector('[data-planner]')
            const scroll = planner?.querySelector('.planner-scroll')
            const card = planner?.querySelector(`[data-match-id="${event.detail.matchId}"]`)
            const zone = card?.closest('.planner-dropzone')
            if (!scroll || !card || !zone) return

            const headingHeight = planner.querySelector('.planner-headings')?.offsetHeight ?? 68
            scroll.scrollTo({
                top: Math.max(0, headingHeight + card.offsetTop - 72),
                left: Math.max(0, zone.offsetLeft - 76),
                behavior: 'smooth',
            })
            card.classList.add('is-search-focus')
            setTimeout(() => card.classList.remove('is-search-focus'), 3000)
        }, 100)
    })
}

initializePlannerEvents()
initializePlanner()
document.addEventListener('DOMContentLoaded', initializePlanner)
document.addEventListener('livewire:navigated', initializePlanner)
document.addEventListener('livewire:initialized', initializePlanner)
