interface GaugeCardProps {
  icon: string;
  title: string;
  value: number | null | undefined;
  unit: string;
  maxValue: number;
  color?: "green" | "blue" | "orange";
}

export default function GaugeCard({
  icon,
  title,
  value,
  unit,
  maxValue,
  color = "green",
}: GaugeCardProps) {
  const hasValue = value !== null && value !== undefined;
  const percentage = hasValue ? Math.min((value / maxValue) * 100, 100) : 0;

  const colorMap = {
    green: "#059669",
    blue: "#2563eb",
    orange: "#f97316",
  };

  return (
    <div style={{
      background: "white",
      borderRadius: "12px",
      boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
      padding: "24px"
    }}>
      {/* 헤더 */}
      <div style={{
        display: "flex",
        alignItems: "center",
        gap: "12px",
        marginBottom: "16px"
      }}>
        <span style={{ fontSize: "1.875rem" }}>{icon}</span>
        <h3 style={{
          fontSize: "1.125rem",
          fontWeight: "600",
          color: "#1f2937"
        }}>{title}</h3>
      </div>

      {/* 값 표시 */}
      <div style={{ textAlign: "center", marginBottom: "16px" }}>
        <div style={{
          fontSize: "3rem",
          fontWeight: "700",
          color: hasValue ? "#059669" : "#d1d5db"
        }}>
          {hasValue ? value : "-"}
          {hasValue && (
            <span style={{
              fontSize: "1.5rem",
              fontWeight: "400",
              marginLeft: "8px"
            }}>{unit}</span>
          )}
        </div>
        {!hasValue && (
          <div style={{
            fontSize: "0.875rem",
            color: "#6b7280",
            marginTop: "8px"
          }}>측정 대기중</div>
        )}
      </div>

      {/* 게이지 바 */}
      <div style={{
        width: "100%",
        background: "#e5e7eb",
        borderRadius: "9999px",
        height: "12px",
        overflow: "hidden"
      }}>
        <div style={{
          height: "100%",
          background: colorMap[color],
          width: `${percentage}%`,
          transition: "all 0.5s"
        }} />
      </div>
    </div>
  );
}
