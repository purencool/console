<?php
namespace App\UI\Component\Editor;

use App\State\AppState;

class EditorAiRefactorProcessor
{
    /**
     * Gathers the document lines from the active workspace canvas, transmits the instruction
     * bundle to your AI backend, and appends the generated response block back down.
     */
    public function execute(
        array $services,
        AppState $state,
        object $editor,
    ): void {
        /** @var \App\Services\JsonService $jsonService */
        $jsonService = $services["json"];

        $instruction = method_exists($editor, "getAiPromptText")
            ? $editor->getAiPromptText()
            : "";
        if (trim($instruction) === "") {
            return;
        }

        $originalCodeLines = $state->getEditorLines();
        $compiledPrompt =
            "Instruction: " .
            $instruction .
            "\n\nTarget Document Context Code:\n" .
            implode("\n", $originalCodeLines);

        if (method_exists($editor, "setAiPromptText")) {
            $editor->setAiPromptText(
                "🤖 Submitting instruction to AI agent...",
            );
        }

        if (method_exists($state, "setProcessing")) {
            $state->setProcessing(true);
        }

        try {
            $result = $jsonService->makeCall($services, "ai", $compiledPrompt);

            if (isset($result["success"]) && $result["success"]) {
                $commentPrefix =
                    pathinfo(
                        $state->getEditorFilePath(),
                        PATHINFO_EXTENSION,
                    ) === "py"
                        ? "# "
                        : "// ";

                $appendedCanvasPool = array_merge(
                    $originalCodeLines,
                    [
                        "",
                        $commentPrefix .
                        "==========================================",
                        $commentPrefix . "❓ AI INLINE PROMPT: " . $instruction,
                        $commentPrefix .
                        "========================================--",
                    ],
                    $result["output"],
                    [
                        $commentPrefix .
                        "==========================================",
                    ],
                );

                $state->setEditorLines($appendedCanvasPool);

                if (method_exists($editor, "setAiPromptOpen")) {
                    $editor->setAiPromptOpen(false);
                }

                $state->setEditorRow(count($appendedCanvasPool) - 1);
                $state->setEditorCol(0);
            } else {
                if (method_exists($editor, "setAiPromptText")) {
                    $editor->setAiPromptText(
                        "❌ Error: " .
                            implode(" ", $result["output"] ?? ["Failed."]),
                    );
                }
            }
        } catch (\Throwable $e) {
            if (method_exists($editor, "setAiPromptText")) {
                $editor->setAiPromptText(
                    "❌ Connection failed: " . $e->getMessage(),
                );
            }
        } finally {
            if (method_exists($state, "setProcessing")) {
                $state->setProcessing(false);
            }
        }
    }
}
