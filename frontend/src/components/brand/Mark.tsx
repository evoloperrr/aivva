export function Mark({ className = "" }: { className?: string }) {
  return (
    <span className={`mark tracking-[0.22em] ${className}`} aria-label="AIVVA">
      △I▽▽△
    </span>
  );
}
