import { useState } from "react";

export default function Alert({ message, type }: { message: string, type: "success" | "error" | "warning" }) {
    const [visible, setVisible] = useState(true);

    return (
        <div className={`alert alert-${type} alert-dismissible fade show ${visible ? "d-block" : "d-none"}`} role="alert">
            {message}
            ho
        </div>
    );
}