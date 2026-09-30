/**
 * Two-finger pinch scale relative to the distance recorded at gesture start.
 * Never writes TouchEvent.scale — that property is read-only in some iPad
 * browsers (DuckDuckGo and others whose UA does not contain "iPad").
 */
export function getPinchScale(
  e: { touches?: ArrayLike<{ pageX: number; pageY: number }> },
  scaleDistStart: number,
): number {
  if (!e.touches || e.touches.length < 2 || !scaleDistStart) {
    return 1
  }

  return (
    Math.hypot(
      e.touches[0].pageX - e.touches[1].pageX,
      e.touches[0].pageY - e.touches[1].pageY,
    ) / scaleDistStart
  )
}
