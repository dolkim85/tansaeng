interface SensorRowProps {
  label: string;
  value: number | null | undefined;
  unit: string;
}

export default function SensorRow({ label, value, unit }: SensorRowProps) {
  const hasValue = value !== null && value !== undefined;

  return (
    <div style={{
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      paddingTop: "8px",
      paddingBottom: "8px",
      borderBottom: "1px solid #f3f4f6"
    }}>
      <dt style={{
        fontSize: "0.875rem",
        color: "#4b5563"
      }}>{label}</dt>
      <dd style={{
        fontSize: "1rem",
        fontWeight: "600",
        color: hasValue ? "#1f2937" : "#9ca3af"
      }}>
        {hasValue ? `${value}${unit}` : "측정 대기중"}
      </dd>
    </div>
  );
}
